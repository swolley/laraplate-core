<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Carbon\Carbon;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

use function Laravel\Prompts\text;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Models\License;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class HandleLicensesCommand extends Command
{
    protected $signature = 'auth:licenses';

    protected $description = 'Renew, add or delete user licenses. <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle()
    {
        try {
            return $this->db->transaction(function (): int {
                $number = 0;
                $valid_to = null;

                $licenses_groups = License::query()->groupBy('valid_to')
                    ->select($this->db->raw('valid_to'), $this->db->raw('count(*) as count'))
                    ->get();
                $licenses_count = (int) $licenses_groups->reduce(fn (int $total, object $current): int|float => $total + $current->count, 0);

                if ($licenses_groups->isEmpty()) {
                    $this->output->info('No licenses found');
                } else {
                    $this->output->info('Current licenses status');
                    table(
                        ['Status', 'Expiration', 'Licenses Qt.'],
                        $licenses_groups->map(fn ($data): array => [
                            $data->valid_to && today()->greaterThan($data->valid_to) ? $data->valid_to : ($data->valid_to ? 'expired' : 'perpetual'),
                            $data->valid_to,
                            $data->count,
                        ]),
                    );
                }

                $choices = ['list', 'add', 'close'];

                if ($licenses_groups->isNotEmpty()) {
                    $choices[] = 'renew';
                }
                $action = select('Choose an action', $choices);

                if ($action !== 'list') {
                    $number = (int) text(
                        "Number of licenses to {$action}",
                        validate: fn ($value) => $this->validationCallback('number', $value, ['number' => 'numeric|min:0']),
                    );

                    if ($number === 0) {
                        return BaseCommand::SUCCESS;
                    }

                    $validations = new License()->getOperationRules('create');

                    $valid_to = text(
                        "Specify an expiring date, otherwise it'll be " . ($action === 'close' ? 'today' : 'perpetual'),
                        'yyyy-mm-dd',
                        validate: fn ($value) => $this->validationCallback('valid_to', $value, $validations),
                    );
                    $valid_to = $valid_to !== '' && $valid_to !== '0' ? new Carbon($valid_to) : null;
                }

                switch ($action) {
                    case 'renew':
                        $this->renewLicenses($number, $licenses_count, $valid_to);
                        break;
                    case 'add':
                        $this->addLicenses($number, $valid_to);
                        break;
                    case 'close':
                        $this->closeLicenses($number, $valid_to);
                        break;
                    case 'list':
                        $this->listLicenses();
                        break;
                }

                $this->output->info('User class is not Modules\Core\Models\User');
                $this->db->commit();

                return BaseCommand::SUCCESS;
            });
        } catch (Throwable $ex) {
            $this->output->error($ex->getMessage());

            return BaseCommand::FAILURE;
        }
    }

    private function listLicenses(): void
    {
        $licenses = License::with('user')->get();
        $remapped = [];

        foreach ($licenses as $license) {
            $remapped[] = [$license->id, $license->valid_to, $license->user->name];
        }
        table(['License', 'Expiration', 'User'], $remapped);
        $this->output->info('Current max sessions available: ' . (Setting::query()->where('name', 'maxConcurrentSessions')->first()?->value ?? 'unlimited'));
    }

    private function renewLicenses(int $number, int $licensesCount, ?Carbon $validTo): void
    {
        $updated = License::query()->take($number)->update([
            'valid_from' => today(),
            'valid_to' => $validTo,
        ]);
        $message = "Renewed {$updated} licenses";
        $this->output->info($message);
        Log::info($message);

        if ($licensesCount > $number) {
            $closed = License::query()->offset($number)->update([
                'valid_to' => $validTo ?? today(),
            ]);
            $message = "Closed {$closed} licenses";
            $this->output->info($message);
            Log::info($message);
        } elseif ($licensesCount < $number) {
            $difference = $licensesCount - $number;

            if (confirm("{$licensesCount} licenses found. Do you confirm {$difference} licenses creation?")) {
                License::factory()->count($difference)->create();
                $message = "Added {$difference} new licenses";
                $this->output->info($message);
                Log::info($message);
            }
        }
    }

    private function addLicenses(int $number, ?Carbon $validTo): void
    {
        $query = License::expired()->take($number);
        $expired = $query->count();

        if ($expired && confirm("Found {$expired} expired licenses. Would you renew them, before creating new ones?")) {
            $query->update([
                'valid_to' => $validTo ?? null,
            ]);
            $message = "Renewed {$expired} licenses";
            $this->output->info($message);
            Log::info($message);
            $number -= $expired;
        }

        if ($number !== 0) {
            License::factory($number)->create([
                'valid_to' => $validTo ?? today(),
            ]);
            $message = "Added {$number} new licenses";
            $this->output->info($message);
            Log::info($message);
        }
    }

    private function closeLicenses(int $number, ?Carbon $validTo): void
    {
        $query = License::query()->take($number);
        $closed = $query->count();
        $query->update([
            'valid_to' => $validTo ?? today(),
        ]);
        $message = "Closed {$closed} licenses";
        $this->output->info($message);
        Log::info($message);
    }

    private function validationCallback(string $attribute, string $value, array $validations)
    {
        if (! array_key_exists($attribute, $validations)) {
            return null;
        }

        $validator = Validator::make([$attribute => $value], array_filter($validations, fn ($k): bool => $k === $attribute, ARRAY_FILTER_USE_KEY))->stopOnFirstFailure(true);

        if (! $validator->passes()) {
            return $validator->messages()->first();
        }

        return null;
    }
}
