<?php

declare(strict_types=1);

namespace Modules\Core\Import\Importers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Core\Import\Contracts\EntityImporterInterface;
use Modules\Core\Import\Enums\ImportRowOutcome;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Import\Support\ImportRowContext;
use Modules\Core\Import\Support\RecordOriginRegistry;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\Core\Import\ValueObjects\ImportField;
use Modules\Core\Models\User;
use Override;

/**
 * Reference {@see EntityImporterInterface} for a Core entity: bulk-imports users by
 * email. It doubles as the worked example a module importer mirrors — validate the
 * mapped row, upsert idempotently (email is the identity), stamp provenance in
 * `core_record_origins`, and report the create/update outcome.
 *
 * A row with no password creates the user with a random one, so imported accounts
 * exist and reset their password rather than being importable with a blank secret.
 */
final readonly class UserImporter implements EntityImporterInterface
{
    public function __construct(private RecordOriginRegistry $origins) {}

    #[Override]
    public function key(): string
    {
        return 'core.user';
    }

    #[Override]
    public function label(): string
    {
        return 'Users';
    }

    /**
     * @return list<ImportField>
     */
    #[Override]
    public function fields(): array
    {
        return [
            new ImportField('name', 'Name', required: true),
            new ImportField('email', 'Email', required: true, aliases: ['e-mail', 'mail']),
            new ImportField('username', 'Username', aliases: ['user', 'login']),
            new ImportField('password', 'Password'),
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    #[Override]
    public function import(array $row, ImportRowContext $context): ImportRowOutcome
    {
        $name = mb_trim($row['name'] ?? '');
        $email = mb_trim($row['email'] ?? '');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email']],
        );

        if ($validator->fails()) {
            throw RowImportException::withErrors($validator->errors()->messages());
        }

        $user = User::query()->where('email', $email)->first();
        $outcome = $user instanceof User ? ImportRowOutcome::Updated : ImportRowOutcome::Created;
        $user ??= new User(['email' => $email]);

        $user->name = $name;

        if (! $user->exists) {
            $user->username = $this->uniqueUsername($row['username'] ?? '', $email);
        }

        $password = $row['password'] ?? '';

        if ($password !== '') {
            $user->password = $password;
        } elseif (! $user->exists) {
            $user->password = Str::password(24);
        }

        $user->save();

        $this->origins->register(
            $user,
            new ExternalRecordIdentity($context->sourceKey(), $email, hash('sha256', (string) json_encode($row))),
            $context->session->original_filename,
        );

        return $outcome;
    }

    /**
     * The provided username, or the email local-part, made unique by a short
     * email-derived suffix on collision (username is required and unique on create).
     */
    private function uniqueUsername(string $provided, string $email): string
    {
        $base = mb_trim($provided);

        if ($base === '') {
            $base = (string) Str::of($email)->before('@')->slug();
            $base = $base === '' ? 'user' : $base;
        }

        if (! User::query()->where('username', $base)->exists()) {
            return $base;
        }

        return $base . '-' . mb_substr(hash('sha256', $email), 0, 6);
    }
}
