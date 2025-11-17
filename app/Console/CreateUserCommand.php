<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HasCommandUtils;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Overrides\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class CreateUserCommand extends Command
{
    use HasCommandUtils;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auth:create-user';

    /**
     * The console command description.
     */
    protected $description = 'Create new user <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $total_users_created = 0;

        try {
            /** @var User $user */
            $user = new (user_class());
            $fillables = $user->getFillable();
            $validations = $user->getOperationRules('create');
            $all_roles = Role::query()->get(['id', 'name'])->pluck('name', 'id');
            $all_permissions = Permission::query()->get(['id', 'name'])->pluck('name', 'id');

            $created_users = [];

            do {
                DB::transaction(function () use ($user, $fillables, $validations, $all_roles, $all_permissions, &$created_users, &$total_users_created): void {
                    /** @var User $user */
                    $user = new (user_class());
                    $password = '';

                    foreach ($fillables as &$attribute) {
                        if ($attribute !== 'password') {
                            $suggestion = $attribute === 'email' ? '@' . str_replace('_', '', Str::slug(config('app.name'))) . '.com' : '';
                            $options = null;

                            if (isset($validations[$attribute])) {
                                if (is_string($validations[$attribute]) && preg_match('/in:([^|]*)/', $validations[$attribute], $matches)) {
                                    $options = explode(',', $matches[1]);
                                } elseif (is_array($validations[$attribute])) {
                                    $found = array_filter($validations[$attribute], fn ($v): bool => is_string($v) && Str::contains($v, 'in:'));

                                    if ($found !== []) {
                                        preg_match('/in:([^|]*)/', (string) head($found), $matches);
                                        $options = explode(',', $matches[1]);
                                    }
                                }
                            }

                            if ($options !== null) {
                                $answer = search(ucfirst($attribute), fn ($value): array => array_filter($options, fn ($o): bool => str_starts_with($o, $value)));
                            } else {
                                $answer = text(ucfirst($attribute), $suggestion, required: true, validate: fn (string $value) => $this->validationCallback($attribute, $value, $validations));
                            }
                        } else {
                            $answer = password(ucfirst($attribute), 'Type a password or let blank to randomly generate it', false, fn (string $value) => $value === '' ? null : $this->validationCallback($attribute, $value, $validations));

                            if ($answer !== '') {
                                password('Confirm ' . $attribute, required: true, validate: fn (string $value) => $this->validationCallback($attribute, $value, ['password' => 'in:' . $answer]));
                            } else {
                                $answer = Str::password();
                            }

                            $password = $answer;
                        }

                        $user->{$attribute} = $answer;
                    }

                    do {
                        $roles = multiselect('Roles', $all_roles, required: false);
                    } while ($roles === [] || confirm("You didn't choose any role, do you want to continue?", false));

                    $permissions = confirm('Do you want to specify custom user permissions', false, hint: 'user already inherits choosen Roles permissions') ? multiselect('Permissions', $all_permissions, required: false) : [];

                    $user->save();
                    $user->roles()->sync($roles);

                    if ($permissions !== []) {
                        $user->permissions()->sync($permissions);
                    }

                    $this->output->info('User created');
                    $total_users_created++;

                    $created_users[] = [
                        'username' => $user->username,
                        'name' => $user->name,
                        'email' => $user->email,
                        'password' => $password,
                        'roles' => $all_roles->filter(fn ($name, $id): bool => in_array($id, $roles, true))->pluck('name')->implode(', '),
                        'permissions' => $all_permissions->filter(fn ($name, $id): bool => in_array($id, $permissions, true))->pluck('name')->implode(', '),
                    ];

                    gc_collect_cycles();
                });
            } while (confirm('Do you want to create another user?', false));

            $this->output->info(sprintf('Created %d users', $total_users_created));

            table(['Username', 'Name', 'Email', 'Password', 'Roles', 'Permissions'], $created_users);

            return BaseCommand::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return BaseCommand::FAILURE;
        }
    }
}
