# Setup project

**Step 1:** On this project
```
php artisan migrate --seed
```

**Step 2 (if you use Nova Admin):** Go to `grandcalendar-admin:v4` and setup then run
```
php artisan migrate
```

To create Nova tables.

## Test Users

Please check `UserSeeder.php` for more info

Admin (email/password)

```
admin@grandcalendar.io/password
```

Test user (email/password)

```
user1@grandcalendar.io/password
```