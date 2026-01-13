# Justice Hammer DBMS

A database management system for case tracking and evidence management, built with PHP 8.5 and MySQL 8.0.

## Requirements

- PHP 8.5.1 or higher
- MySQL 8.0.44 or higher
- XAMPP (recommended for local development)
- Web server (Apache/Nginx) or PHP built-in server

## Setup Instructions

### 1. Database Setup

Create two databases in MySQL:

**Primary Database (OLTP):**
```bash
mysql -u root -p < sql/schema.sql
```

**Analytics Database:**
```bash
mysql -u root -p < sql/analytics_schema.sql
```

Alternatively, you can run these SQL files through phpMyAdmin or MySQL Workbench.

### 2. Environment Configuration

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` with your database credentials:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=justice_hammer
   DB_USER=root
   DB_PASS=your_password

   ANALYTICS_DB_HOST=127.0.0.1
   ANALYTICS_DB_PORT=3306
   ANALYTICS_DB_NAME=justice_hammer_analytics
   ANALYTICS_DB_USER=root
   ANALYTICS_DB_PASS=your_password

   UPLOADS_DIR=uploads
   BASE_URL=http://127.0.0.1:8000
   ```

### 3. Create Uploads Directory

Ensure the `uploads/` directory exists and is writable:
```bash
mkdir uploads
chmod 755 uploads  # On Linux/Mac
# On Windows, ensure the directory has write permissions for the web server user
```

### 4. Start the Development Server

Using PHP built-in server:
```bash
php -S 127.0.0.1:8000 -t public
```

Or configure your XAMPP Apache to point to the project root.

### 5. Health Check

Verify database connectivity:
```
http://127.0.0.1:8000/index.php
```

You should see:
```
Justice Hammer DBMS - Health Check
===================================

Primary DB: Connected
Analytics DB: Connected

✓ All systems operational
```

If you see connection errors, verify:
- MySQL is running
- Database credentials in `.env` are correct
- Both databases exist (`justice_hammer` and `justice_hammer_analytics`)

## Project Structure

```
/
  /public          - Web-accessible files (health check)
  /includes        - PHP includes (database connections, auth, functions)
  /api             - API endpoints
  /pages           - Web pages
  /sql             - Database schemas and seeds
  /tools           - CLI utilities
  /test            - Test scripts and examples
  /uploads         - Uploaded evidence files
  .env             - Environment variables (not in git)
  .env.example     - Example environment file
```

## Database Schema

### Primary Database (`justice_hammer`)
- `users` - User accounts (LITIGANT, LAWYER, OFFICIAL, ADMIN)
- `cases` - Case records with tracking codes
- `evidence` - Evidence files with SHA256 hashes
- `timeline` - Audit trail of case events

### Analytics Database (`justice_hammer_analytics`)
- `case_snapshots` - Aggregated case data
- `monthly_stats` - Monthly statistics by district

## Next Steps

After completing Phase 1, proceed to:
- Phase 2: Authentication & Case Creation
- Phase 3: Evidence & Workflow Logic
- Phase 4: Analytics & Export

## Notes

- This project uses raw SQL with PDO (no ORMs)
- All database operations use prepared statements
- Environment variables are loaded from `.env` file
- Health check endpoint tests both database connections
