# Database Portability

This project uses Doctrine entities plus a normalization migration to keep the messaging schema stable across laptops.

## Important note about `conversation_user`

`conversation_user` is a pure join table between:

- `conversation.id_conversation`
- `user_app.id_user`

It does not have its own Doctrine entity on purpose.

This is the correct setup today because:

- the table only stores the relationship
- there are no extra columns like `joined_at`, `role_in_conversation`, `is_muted`, or `last_read_at`
- Doctrine manages it through the `ManyToMany` mapping in:
  - [src/Entity/Conversation.php](/abs/path/c:/Users/alakh/OneDrive/Bureau/WebDev010/WebDev01/src/Entity/Conversation.php)
  - [src/Entity/UserApp.php](/abs/path/c:/Users/alakh/OneDrive/Bureau/WebDev010/WebDev01/src/Entity/UserApp.php)

If later you want extra metadata in the join table, then it should be refactored into a real entity such as `ConversationParticipant`.

## What was normalized

The migration [migrations/Version20260410123000.php](/abs/path/c:/Users/alakh/OneDrive/Bureau/WebDev010/WebDev01/migrations/Version20260410123000.php) now makes sure that:

- `conversation_user` exists
- `conversation_user.id_conversation` has a foreign key to `conversation.id_conversation`
- `conversation_user.id_user` has a foreign key to `user_app.id_user`
- `conversation.id_createur` exists and is linked to `user_app.id_user`
- `message.date_modifier` exists
- `message.reactions` exists
- `message.attachments` exists

## Recommended setup on another laptop

1. Clone the project.
2. Run `composer install`.
3. Create the database.
4. Put your local DB credentials in `.env.local`.
5. Run:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

## If your environment already has old partial tables

Still run:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

The normalization migration is designed to complete the missing messaging structure instead of assuming a perfectly clean schema.

## Best practice

For portability, avoid hardcoded machine paths in the database or in config. Prefer:

- relative uploads inside the project
- `.env.local` for machine-specific settings
- Doctrine migrations for schema changes
