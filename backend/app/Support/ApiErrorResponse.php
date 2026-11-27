<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

final class ApiErrorResponse
{
    public static function normalize(Response $response, string $requestId): Response
    {
        $status = $response->getStatusCode();

        if ($status < 400) {
            return $response;
        }

        $content = $response->getContent();
        $payload = is_string($content)
            ? json_decode($content, true)
            : null;

        $payload = is_array($payload) ? $payload : [];
        $existingError = is_array($payload['error'] ?? null)
            ? $payload['error']
            : [];

        $defaults = [
            400 => ['BAD_REQUEST', 'Yêu cầu không hợp lệ.'],
            401 => ['UNAUTHENTICATED', 'Vui lòng đăng nhập để tiếp tục.'],
            403 => ['FORBIDDEN', 'Bạn không có quyền thực hiện thao tác này.'],
            404 => ['NOT_FOUND', 'Không tìm thấy tài nguyên được yêu cầu.'],
            405 => ['METHOD_NOT_ALLOWED', 'Phương thức gửi yêu cầu không được hỗ trợ.'],
            409 => ['CONFLICT', 'Yêu cầu xung đột với trạng thái hiện tại.'],
            419 => ['SESSION_EXPIRED', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.'],
            422 => ['VALIDATION_ERROR', 'Dữ liệu gửi lên chưa hợp lệ.'],
            429 => ['TOO_MANY_REQUESTS', 'Bạn gửi yêu cầu quá nhanh. Vui lòng thử lại sau.'],
        ];

        [$defaultCode, $defaultMessage] = $defaults[$status]
            ?? ['REQUEST_FAILED', 'Không thể hoàn thành yêu cầu.'];

        $isServerError = $status >= 500;

        $error = [
            'code' => $isServerError
                ? ($existingError['code'] ?? 'INTERNAL_SERVER_ERROR')
                : ($existingError['code'] ?? $defaultCode),
            // Với lỗi 5xx, không trả message nội bộ hoặc nội dung exception cho client.
            'message' => $isServerError
                ? 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.'
                : ($existingError['message'] ?? $defaultMessage),
            'request_id' => $requestId,
        ];

        if (! $isServerError) {
            $details = $existingError['details']
                ?? ($status === 422 ? ($payload['errors'] ?? []) : []);

            if (is_array($details) && $details !== []) {
                $error['details'] = $details;
            }
        }

        $response->setContent(json_encode(
            ['error' => $error],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');

        return $response;
    }
}
