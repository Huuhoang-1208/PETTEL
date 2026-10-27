# PETTEL domain modules

Create a module when its first feature is implemented, using this shape:

```text
app/Domain/Booking/
├── Actions/
├── Data/
├── Events/
├── Models/
└── Policies/
```

Keep JSON API controllers and Form Requests in `app/Http`; keep the Vue SPA in the repository-level `frontend/` directory and Filament resources in Laravel's admin panel. Database state is authoritative for booking and payment transitions; dispatch side effects after commit.

Planned P0 domains are Identity, Pet, Listing, Discovery, Booking, Payment, Messaging, Review, and Reports. Create each module as work begins instead of adding empty placeholders.
