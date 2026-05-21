MyCash

REST API untuk sistem Point of Sales MyCash. Dibangun dengan Laravel 13 + Sanctum, menggunakan service layer untuk pisahkan business logic dari controller.
Tech Stack
PHP 8.3 + Laravel 13
Laravel Sanctum — token-based auth
SQLite (default) / MySQL
Maatwebsite Excel — export laporan .xlsx
Predis — Redis client (opsional, untuk caching)
Cara Jalankan
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

Database default pakai SQLite, file-nya ada di database/database.sqlite dan dibuat otomatis waktu migrate.
Ganti ke MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mycash
DB_USERNAME=root
DB_PASSWORD=

Environment penting lainnya
APP_URL=http://localhost:8000

# Storage untuk gambar produk

FILESYSTEM_DISK=public

# Sanctum

SANCTUM_STATEFUL_DOMAINS=localhost:5173

Setelah set FILESYSTEM_DISK=public, jalankan:
php artisan storage:link

Struktur Folder
app/
├── Enums/ # UserRole enum
├── Exports/ # Class export Excel (Maatwebsite)
├── Helpers/ # ApiMessage, ApiLogger
├── Http/
│ ├── Controllers/Api/ # Satu controller per resource
│ ├── Middleware/ # CheckRole
│ ├── Requests/ # Form request + validasi
│ └── Resources/ # API resource transformer
├── Models/ # Eloquent models
├── Services/ # Business logic
└── Traits/ # Reusable trait

API
Base URL: /api/v1
Semua endpoint protected butuh header:
Authorization: Bearer <token>

Auth
POST /auth/register
POST /auth/login
POST /auth/logout
GET /auth/me

Resources
Endpoint : Buka -> POS Finpro.postman_collection.json
Role hierarchy: superadmin > owner > admin > cashier.
Role diset lewat middleware role: yang didefinisikan di bootstrap/app.php:
->withMiddleware(function (Middleware $middleware) {
$middleware->alias(['role' => CheckRole::class]);
})

Pemakaian di routes:
Route::middleware('role:superadmin,owner')->group(function () {
Route::apiResource('users', UserController::class);
});

Database
Relasi utama:
Business
└── Outlet (1 business, banyak outlet)
├── User (kasir/admin di-assign ke outlet)
├── Stock (per produk per outlet)
└── Shift
└── Transaction
└── TransactionItem

Business dan User owner dibuat sekaligus saat register. Kasir dibuat oleh owner/admin dan di-assign ke outlet tertentu.
Catatan
Soft delete dipakai di Product dan Outlet — data tidak langsung hilang dari database.
Stok berkurang otomatis saat transaksi dibuat, dan kembali kalau transaksi di-refund.
Export Excel via GET /reports/sales/export menggunakan Maatwebsite Excel, response berupa file .xlsx.
ApiMessage helper di app/Helpers/ dipakai konsisten untuk format response JSON supaya frontendnya mudah handle.
