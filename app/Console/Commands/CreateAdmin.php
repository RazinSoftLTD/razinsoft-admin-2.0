<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the first admin on a fresh install.
 *
 * A buyer who has just uploaded the files has no way in otherwise, and shipping a default
 * account with a known password is how installations get taken over in the first week.
 */
class CreateAdmin extends Command
{
    protected $signature = 'smartdesk:admin
        {--name= : The display name}
        {--email= : Sign-in address}';

    protected $description = 'Create an admin account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');

        // Asked for, never passed as an option: a password on the command line ends up in the
        // shell history and in the process list.
        $password = $this->secret('Password (at least 8 characters)');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($this->secret('Type it again') !== $password) {
            $this->error('Those did not match.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
            'status' => 'active',
        ]);

        $this->newLine();
        $this->info("Admin created. Sign in at ".rtrim((string) config('app.url'), '/')."/admin/login");

        return self::SUCCESS;
    }
}
