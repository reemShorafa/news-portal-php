# Administrator credential recovery

## Current administrator accounts

XAMPP and Docker use separate database instances. Resetting the XAMPP account does not reset the Docker account, or vice versa. The utility lists the administrator accounts in the database it connects to, without placing account details in source code or documentation.

## Why setup-admin.php does not reset passwords

`setup-admin.php` is a first-run installer. It accepts requests only from localhost and returns HTTP 410 as soon as any administrator exists. This protection remains unchanged and must not be bypassed for account recovery.

The login page looks up the submitted email, requires `status = active`, and checks the password with PHP's `password_verify()`.

## Recovery utility

`scripts/reset-admin.php` can run only under PHP CLI. A web request receives an empty 404 response. It:

1. Lists existing administrator accounts and selects one (automatically when there is only one).
2. Prompts for a new email.
3. Reads and confirms the new password without displaying it.
4. Requires at least 12 characters, including uppercase, lowercase, and a number—the same policy as initial setup.
5. Rejects an email already assigned to another user.
6. Creates the hash using `password_hash($password, PASSWORD_DEFAULT)`.
7. Updates only the selected administrator's email, password, status, and update timestamp.
8. Uses a transaction and verifies the saved hash with `password_verify()` before committing.

No password is accepted as a command-line argument, so it is not stored in shell history or process arguments.

## Reset the XAMPP administrator

Open PowerShell in the project directory and run:

```powershell
& 'C:\xampp\php\php.exe' .\scripts\reset-admin.php
```

Enter the new email and password at the prompts. Then sign in at:

```text
http://localhost/news-portal/login.php
```

## Reset the Docker administrator

Ensure the containers are running, then run this from the project directory in an interactive terminal:

```powershell
docker compose exec app php scripts/reset-admin.php
```

Enter the new email and password at the prompts. Then sign in at:

```text
http://localhost:8080/login.php
```

Docker's database settings come from the ignored local `.env` file (`DB_HOST` is supplied as `db` by Compose); XAMPP uses the local defaults in `config/database.php` (`127.0.0.1`, root with no password). Therefore the same script reaches the correct database depending on where PHP executes.

## Verification

After each reset, the utility prints a success message only after rereading the account and verifying the new hash. Complete an end-to-end check by logging in through that environment's login URL. A successful login redirects to its administration dashboard.

If different credentials are desired for XAMPP and Docker, run the utility once in each environment and enter the appropriate values. Never place the chosen password in this document, the source code, Compose configuration, or a Git commit.
