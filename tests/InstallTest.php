<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Env;

/** Instalação pelo navegador: escondida sem token, cria só o primeiro administrador. */
final class InstallTest extends TestCase
{
    private const TOKEN = 'token-de-instalacao-com-mais-de-32-caracteres';

    public function setUp(): void
    {
        parent::setUp();
        Env::set('SETUP_TOKEN', self::TOKEN);
    }

    public function testHiddenWithoutValidToken(): void
    {
        $br = $this->browser();
        $this->assertStatus(404, $br->get('/instalacao'));
        $this->assertStatus(404, $br->get('/instalacao', ['token' => 'errado']));
        Env::set('SETUP_TOKEN', 'curto');
        $this->assertStatus(404, $br->get('/instalacao', ['token' => 'curto']), 'token curto desativa o instalador');
        Env::set('SETUP_TOKEN', '');
        $this->assertStatus(404, $br->get('/instalacao', ['token' => '']));
    }

    public function testCreatesFirstAdminOnlyOnce(): void
    {
        $br = $this->browser();
        $page = $br->get('/instalacao', ['token' => self::TOKEN]);
        $this->assertStatus(200, $page);
        $this->assertContains('Primeiro administrador', $page->body);
        $this->assertStatus(303, $br->post('/instalacao', ['token' => self::TOKEN, 'name' => 'Admin', 'email' => 'admin@empresa.test', 'password' => 'senha-muito-forte-1', 'password_confirmation' => 'senha-muito-forte-1']));
        $this->assertSame('admin', $this->db->value("SELECT platform_role FROM users WHERE email = 'admin@empresa.test'"));
        $after = $br->get('/instalacao', ['token' => self::TOKEN]);
        $this->assertNotContains('Primeiro administrador', $after->body);
        $this->assertContains('cron.php', $after->body);
        // Segunda tentativa não cria outro administrador
        $br->post('/instalacao', ['token' => self::TOKEN, 'name' => 'Intruso', 'email' => 'intruso@x.test', 'password' => 'senha-muito-forte-2', 'password_confirmation' => 'senha-muito-forte-2']);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM users WHERE platform_role = 'admin'"));
        $this->assertRedirectTo('/plataforma', $this->browser()->login('admin@empresa.test', 'senha-muito-forte-1'));
    }

    public function testMigrationCanBeRetriedAfterPartialRun(): void
    {
        // Simula uma tentativa anterior que criou as tabelas mas não registrou a migration.
        $this->db->execute("DELETE FROM schema_migrations WHERE version = '001_schema'");
        $this->assertSame(['001_schema'], \App\Support\Migrator::pending($this->db));
        $this->assertSame(['001_schema'], \App\Support\Migrator::run($this->db));
        $this->assertSame([], \App\Support\Migrator::pending($this->db));
        // E a instalação pelo navegador conclui normalmente nesse estado.
        $this->db->execute("DELETE FROM schema_migrations WHERE version = '001_schema'");
        $br = $this->browser();
        $br->get('/instalacao', ['token' => self::TOKEN]);
        $this->assertStatus(303, $br->post('/instalacao', ['token' => self::TOKEN, 'name' => 'Admin', 'email' => 'adm@x.test', 'password' => 'senha-muito-forte-1', 'password_confirmation' => 'senha-muito-forte-1']));
        $this->assertSame('admin', $this->db->value("SELECT platform_role FROM users WHERE email = 'adm@x.test'"));
    }

    public function testAdminPasswordNeedsTwelveChars(): void
    {
        $br = $this->browser();
        $br->get('/instalacao', ['token' => self::TOKEN]);
        $br->post('/instalacao', ['token' => self::TOKEN, 'name' => 'A', 'email' => 'a@a.test', 'password' => 'onze-chars1', 'password_confirmation' => 'onze-chars1'], true, ['referer' => 'http://localhost/instalacao?token=' . self::TOKEN]);
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM users"));
    }
}
