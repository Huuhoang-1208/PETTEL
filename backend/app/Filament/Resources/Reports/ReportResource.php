<?php

namespace App\Filament\Resources\Reports;

use App\Domain\Report\Actions\ChangeReportStatus;
use App\Domain\Report\Actions\DecideBookingReport;
use App\Domain\Report\Actions\ModerateReportedTarget;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $modelLabel = 'report';

    protected static ?string $pluralModelLabel = 'Reports';

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Report::class);
    }

    // Report là hồ sơ kiểm duyệt; không tạo/sửa/xóa trực tiếp bằng CRUD.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Report ID')
                    ->sortable(),

                TextColumn::make('target_summary')
                    ->label('Target')
                    ->state(fn(Report $record): string => self::targetSummary($record)),

                TextColumn::make('reporter_user_id')
                    ->label('Reporter ID')
                    ->sortable(),

                TextColumn::make('reason_code')
                    ->label('Reason')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'OPEN' => 'Open',
                        'IN_REVIEW' => 'In review',
                        'RESOLVED' => 'Resolved',
                        'DISMISSED' => 'Dismissed',
                    ]),
            ])
            ->recordActions([
                self::statusAction('markInReview', 'Start review', 'IN_REVIEW'),
                self::statusAction('resolve', 'Resolve', 'RESOLVED'),
                self::statusAction('dismiss', 'Dismiss', 'DISMISSED'),

                self::moderationAction('hideTarget', 'Hide target', 'HIDE'),
                self::moderationAction('restoreTarget', 'Restore target', 'RESTORE'),

                self::bookingDecisionAction(),

                Action::make('history')
                    ->label('History')
                    ->icon(Heroicon::OutlinedClock)
                    ->url(fn(Report $record): string => route(
                        'admin.reports.history',
                        ['id' => $record->getKey()],
                    ))
                    ->openUrlInNewTab()
                    ->visible(fn(Report $record): bool => Gate::allows('view', $record)),

                Action::make('evidence')
                    ->label('View permitted evidence')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn(Report $record): string => route(
                        'admin.reports.evidence',
                        ['id' => $record->getKey()],
                    ))
                    ->openUrlInNewTab()
                    ->visible(fn(Report $record): bool => Gate::allows('viewEvidence', $record)),
            ]);
    }

    public static function getPages(): array
    {
        // Chỉ đăng ký trang queue; không có create/edit/view CRUD page.
        return [
            'index' => ListReports::route('/'),
        ];
    }

    private static function targetSummary(Report $record): string
    {
        foreach ([
            'listing_id' => 'LISTING',
            'review_id' => 'REVIEW',
            'message_id' => 'MESSAGE',
            'booking_id' => 'BOOKING',
        ] as $field => $type) {
            if ($record->{$field} !== null) {
                return $type . ' #' . $record->{$field};
            }
        }

        return 'INVALID TARGET';
    }

    private static function statusAction(
        string $name,
        string $label,
        string $toStatus,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000),
            ])
            ->visible(fn(Report $record): bool => self::canChangeTo($record, $toStatus))
            ->action(function (Report $record, array $data, ChangeReportStatus $changeReportStatus, ) use ($toStatus): void {
                Gate::authorize('changeStatus', $record);

                // Booking report chỉ được RESOLVED qua booking decision.
                abort_if(
                    $toStatus === 'RESOLVED' && $record->booking_id !== null,
                    409,
                    'Use the booking decision action for this report.',
                );

                $admin = self::currentAdmin();

                $changeReportStatus->handle(
                    $admin,
                    (string) $record->getKey(),
                    $toStatus,
                    trim((string) $data['reason']),
                    self::requestId(),
                );

                Notification::make()
                    ->title('Report status updated')
                    ->success()
                    ->send();
            });
    }

    private static function canChangeTo(Report $record, string $toStatus): bool
    {
        if (!Gate::allows('changeStatus', $record)) {
            return false;
        }

        return match ($toStatus) {
            'IN_REVIEW' => $record->status === 'OPEN',
            'RESOLVED' => $record->status === 'IN_REVIEW'
                && $record->booking_id === null,
            'DISMISSED' => in_array($record->status, ['OPEN', 'IN_REVIEW'], true),
            default => false,
        };
    }

    private static function moderationAction(
        string $name,
        string $label,
        string $moderationAction,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000),
            ])
            ->visible(fn(Report $record): bool => Gate::allows('moderate', $record))
            ->action(function (Report $record, array $data, ModerateReportedTarget $moderateReportedTarget, ) use ($moderationAction): void {
                Gate::authorize('moderate', $record);

                $moderateReportedTarget->handle(
                    self::currentAdmin(),
                    (string) $record->getKey(),
                    $moderationAction,
                    trim((string) $data['reason']),
                    self::requestId(),
                );

                Notification::make()
                    ->title('Moderation action recorded')
                    ->success()
                    ->send();
            });
    }

    private static function bookingDecisionAction(): Action
    {
        return Action::make('bookingDecision')
            ->label('Booking decision')
            ->schema([
                Select::make('decision')
                    ->options([
                        'COMPLETE' => 'Complete and release',
                        'CANCEL_REFUND' => 'Cancel and refund',
                    ])
                    ->required(),

                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000),
            ])
            ->visible(
                fn(Report $record): bool =>
                    $record->booking_id !== null
                    && $record->status === 'IN_REVIEW'
                    && Gate::allows('view', $record)
            )
            ->action(function (Report $record, array $data, DecideBookingReport $decideBookingReport, ): void {
                Gate::authorize('view', $record);

                $result = $decideBookingReport->handle(
                    self::currentAdmin(),
                    (string) $record->getKey(),
                    (string) $data['decision'],
                    trim((string) $data['reason']),
                    self::requestId(),
                );

                Notification::make()
                    ->title('Booking decision submitted')
                    ->body(
                        $result['operation_type'] . ' operation: ' . $result['operation_status'],
                    )
                    ->success()
                    ->send();
            });
    }

    private static function currentAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private static function requestId(): string
    {
        $request = request();
        $id = $request->attributes->get('request_id')
            ?? $request->header('X-Request-ID');

        return is_string($id) && Str::isUuid($id)
            ? $id
            : (string) Str::uuid();
    }
}