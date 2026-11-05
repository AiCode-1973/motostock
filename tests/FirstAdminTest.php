<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Env;
use App\Support\FirstAdmin;

/** Primeiro administrador criado a partir do .env durante a instalação. */
final class FirstAdminTest extends TestCase
{
    private string $file;

    public function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/motostock-env-' . bin2hex(random_bytes(4));
        file_put_contents($this->file, "APP_ENV=production\nADMIN_NAME=Dono\nADMIN_EMAIL=Admin@Empresa.test\nADMIN_PASSWORD=senha-bem-forte-123\nDB_HOST=localhost\n");
        Env::set('ADMIN_NAME', 'Dono');
        Env::set('ADMIN_EMAIL', 'Admin@Empresa.test');
        Env::set('ADMIN_PASSWORD', 'senha-bem-forte-123');
    }

    private function cleanup(): void
    {
        @unlink($this->file);
        foreach (['ADMIN_NAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $k) {
            Env::set($k, '');
        }
    }

    public function testCreatesAdminAndErasesPasswordFromEnvFile(): void
    {
        try {
            $msg = FirstAdmin::fromEnv($this->db, $this->file);
            $this->assertContains('Administrador criado', $msg);
            $this->assertSame('admin', $this->db->value("SELECT platform_role FROM users WHERE email = 'admin@empresa.test'"));
            $env = (string) file_get_contents($this->file);
            $this->assertNotContains('senha-bem-forte-123', $env, 'senha apagada do .env');
            $this->assertContains("ADMIN_PASSWORD=\n", $env);
            $this->assertContains('DB_HOST=localhost', $env, 'resto do .env intacto');
            $this->assertRedirectTo('/plataforma', $this->browser()->login('admin@empresa.test', 'senha-bem-forte-123'));
            // Rodar de novo não cria outro nem troca a senha
            Env::set('ADMIN_PASSWORD', 'outra-senha-diferente-1');
            FirstAdmin::fromEnv($this->db, $this->file);
            $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM users'));
            $this->assertTrue(password_verify('senha-bem-forte-123', (string) $this->db->value('SELECT password_hash FROM users')));
        } finally {
            $this->cleanup();
        }
    }

    public function testWeakPasswordOrNothingConfigured(): void
    {
        try {
            Env::set('ADMIN_PASSWORD', 'curta');
            $this->assertContains('12 caracteres', FirstAdmin::fromEnv($this->db, $this->file));
            $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM users'));
            Env::set('ADMIN_EMAIL', '');
            Env::set('ADMIN_PASSWORD', '');
            $this->assertSame('', FirstAdmin::fromEnv($this->db, $this->file));
        } finally {
            $this->cleanup();
        }
    }
}
