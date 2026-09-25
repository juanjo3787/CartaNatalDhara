<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:password {login} {--create : Crear la cuenta si no existe}')]
#[Description('Establece una contraseña mediante entrada oculta y hashing de Laravel')]
class SetUserPassword extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta este comando en una consola interactiva.');

            return self::FAILURE;
        }
        $user = \App\Models\User::where('email', $this->argument('login'))->first();
        if (! $user && ! $this->option('create')) {
            $this->error('La cuenta no existe. Usa --create para crearla.');

            return self::FAILURE;
        }
        $password = $this->secret('Nueva contraseña (mínimo 12 caracteres)');
        $confirmation = $this->secret('Repite la contraseña');
        if (! is_string($password) || mb_strlen($password) < 12 || strlen($password) > 72 || $password !== $confirmation) {
            $this->error('Las contraseñas deben coincidir y tener entre 12 caracteres y 72 bytes.');

            return self::FAILURE;
        }
        $user ??= new \App\Models\User(['email' => $this->argument('login'), 'name' => $this->argument('login')]);
        $user->password = $password;
        $user->remember_token = null;
        $user->save();
        unset($password, $confirmation);
        $this->info('Contraseña guardada.');

        return self::SUCCESS;
    }
}
