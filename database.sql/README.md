# Database setup

This folder contains the SQL schema used by the Canteenshoping project.

## Import

From a command prompt:

```bash
mysql -u root -p < database.sql/canteen_db.sql
```

Or with XAMPP/MySQL:

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root < "C:\xampp\htdocs\Canteenshoping\database.sql\canteen_db.sql"
```

## Included tables

- admins
- users
- products
- orders
- contacts
- password_resets

The admin seed record is also created automatically:

- username: admin
- password: 12345
