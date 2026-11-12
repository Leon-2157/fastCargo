<?php

namespace App\Console\Commands;

use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\TwoFactorResetService;
use Illuminate\Console\Command;

class ResetTwoFactorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '2fa:reset {email : The email address of the user whose 2FA to reset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emergency reset of Two-Factor Authentication credentials for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] was not found in the database.");

            return self::FAILURE;
        }

        if (! $user->hasTwoFactorEnabled()) {
            $this->warn("User [{$email}] does not have 2-Factor Authentication enabled.");
        }

        TwoFactorResetService::adminReset($user, null, 'CLI');

        $this->info("2-Factor Authentication for [{$email}] ({$user->user_type}) has been successfully reset.");
        $this->line('The user will be directed to configure a fresh authenticator on their next login.');

        return self::SUCCESS;
    }
}
