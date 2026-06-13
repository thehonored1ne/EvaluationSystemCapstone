# Feature Overview
We built a role-based authentication system. The login form accepts custom identifiers. The system directs users to specific dashboards. The admin manages all account creations.

# Technical Logic
The `users` table requires unique employee or student IDs. The Livewire login component queries these identifiers. The system checks credentials using Laravel Auth. Rate limiting blocks excessive login attempts. Spatie middleware routes authenticated users based on assigned roles. 

# File Manifest
- `c:\Users\USER\Herd\evaluationsystem\database\migrations\0001_01_01_000000_create_users_table.php`
- `c:\Users\USER\Herd\evaluationsystem\app\Models\User.php`
- `c:\Users\USER\Herd\evaluationsystem\database\seeders\DatabaseSeeder.php`
- `c:\Users\USER\Herd\evaluationsystem\resources\views\livewire\auth\login.blade.php`
- `c:\Users\USER\Herd\evaluationsystem\routes\auth.php`
- `c:\Users\USER\Herd\evaluationsystem\routes\web.php`
- `c:\Users\USER\Herd\evaluationsystem\bootstrap\app.php`
- `c:\Users\USER\Herd\evaluationsystem\resources\views\dashboard.blade.php`
- `c:\Users\USER\Herd\evaluationsystem\resources\views\welcome.blade.php`
- `c:\Users\USER\Herd\evaluationsystem\.env`

# Dependencies
- `spatie/laravel-permission`

# Current State
The system authenticates users properly. Admin users need an interface to create new accounts. The next module will build the user management panel.