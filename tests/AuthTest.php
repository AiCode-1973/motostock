<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Crypto;
use App\Core\Mailer;
use App\Core\Totp;

final class AuthTest extends TestCase
{
    private array $t;

    public function setUp(): void
    {
        parent::setUp();
        $this->t = $this->tenant('auth');
    }

    public function testLoginWithCorrectAndWrongPassword(): void
    {
        $br = $this->browser();
        $this->assertRedirectTo('/entrar', $br->login($this->t['owner']['email'], 'errada-123456'));
        $this->assertRedirectTo('/entrar', $br->get('/painel'));
        $this->assertRedirectTo('/painel', $br->login($this->t['owner']['email']));
        $this->assertStatus(200, $br->get('/painel'));
        $this->assertTrue(password_verify('senha-forte-123', $this->t['owner']['password_hash']));
        $this->assertFalse(str_contains($this->t['owner']['password_hash'], 'senha-forte'), 'senha guardada como hash');
    }

    public function testBruteForceIsThrottled(): void
    {
        $br = $this->browser();
        for ($i = 0; $i < 5; $i++) {
            $br->login($this->t['owner']['email'], 'errada-' . $i . 'xxxxx');
        }
        $this->assertStatus(429, $br->login($this->t['owner']['email']), 'nem a senha certa entra durante o bloqueio');
        $this->assertSame(5, (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login_failed'"));
    }

    public function testSessionIsRegeneratedAndEndsOnLogout(): void
    {
        $br = $this->browser();
        $br->get('/entrar');
        $before = $br->session['_csrf'] ?? null;
        $br->login($this->t['owner']['email']);
        $this->assertFalse(($br->session['_csrf'] ?? null) === $before, 'token renovado no login');
        $br->post('/sair');
        $this->assertRedirectTo('/entrar', $br->get('/painel'));
    }

    public function testPasswordChangeKillsOtherSessions(): void
    {
        $other = $this->browser();
        $other->login($this->t['owner']['email']);
        $br = $this->browser();
        $br->login($this->t['owner']['email']);
        $br->get('/conta/seguranca');
        $r = $br->post('/conta/senha', ['current_password' => 'senha-forte-123', 'password' => 'nova-senha-forte-1', 'password_confirmation' => 'nova-senha-forte-1']);
        $this->assertRedirectTo('/conta/seguranca', $r);
        \App\Core\Clock::$fixed = time() + 2;
        $this->assertStatus(200, $br->get('/painel'), 'sessão atual continua');
        $this->assertRedirectTo('/entrar', $other->get('/painel'), 'outra sessão encerrada');
    }

    public function testMfaFlow(): void
    {
        $br = $this->browser();
        $br->login($this->t['owner']['email']);
        $br->get('/conta/seguranca');
        $br->post('/conta/mfa/iniciar');
        $secret = $br->session['mfa_setup'];
        $this->assertStatus(303, $br->post('/conta/mfa/ativar', ['code' => Totp::code($secret)]));
        $row = $this->db->fetch('SELECT mfa_enabled, mfa_secret FROM users WHERE id = ?', [$this->t['owner']['id']]);
        $this->assertSame(1, (int) $row['mfa_enabled']);
        $this->assertSame($secret, Crypto::decrypt($row['mfa_secret']), 'segredo cifrado no banco');

        $new = $this->browser();
        $this->assertRedirectTo('/entrar/verificacao', $new->login($this->t['owner']['email']));
        $this->assertRedirectTo('/entrar', $new->get('/painel'), 'sem o código não entra');
        $this->assertRedirectTo('/entrar/verificacao', $new->post('/entrar/verificacao', ['code' => '000000']));
        $this->assertRedirectTo('/painel', $new->post('/entrar/verificacao', ['code' => Totp::code($secret)]));
        $this->assertStatus(200, $new->get('/painel'));
    }

    public function testTotpMatchesRfcVector(): void
    {
        // RFC 6238, segredo ASCII "12345678901234567890", T = 59 → 94287082 (8 dígitos) → 287082 (6 dígitos)
        $this->assertSame('287082', Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
    }

    public function testPasswordResetTokenIsSingleUseAndDoesNotRevealAccounts(): void
    {
        $br = $this->browser();
        $br->get('/esqueci-senha');
        $br->post('/esqueci-senha', ['email' => 'naoexiste@x.test']);
        $this->assertSame([], Mailer::$sent, 'e-mail inexistente não recebe nada');
        $br->post('/esqueci-senha', ['email' => $this->t['owner']['email']]);
        $this->assertSame(1, count(Mailer::$sent));
        preg_match('#redefinir-senha/([A-Za-z0-9_-]+)#', Mailer::$sent[0]['text'], $m);
        $token = $m[1];
        $this->assertNotContains($token, (string) $this->db->value('SELECT token_hash FROM password_resets'), 'banco guarda só o hash do token');
        $this->assertStatus(200, $br->get('/redefinir-senha/' . $token));
        $this->assertRedirectTo('/entrar', $br->post('/redefinir-senha/' . $token, ['password' => 'outra-senha-forte', 'password_confirmation' => 'outra-senha-forte']));
        $this->assertRedirectTo('/painel', $this->browser()->login($this->t['owner']['email'], 'outra-senha-forte'));
        $again = $br->post('/redefinir-senha/' . $token, ['password' => 'mais-uma-senha-x', 'password_confirmation' => 'mais-uma-senha-x'], true, ['referer' => 'http://localhost/redefinir-senha/' . $token]);
        $this->assertStatus(303, $again);
        $this->assertContains('inválido', $again->status === 303 ? (string) ($br->session['_flash'][0]['message'] ?? '') : '');
    }

    public function testSignupCreatesTrialTenantAndOwner(): void
    {
        $plan = $this->plan(['trial_days' => 7]);
        $br = $this->browser();
        $br->get('/cadastro');
        $data = ['trade_name' => 'Moto Nova', 'legal_name' => 'Moto Nova Ltda', 'document' => '11.444.777/0001-61', 'phone' => '(11) 97777-6666',
            'city' => 'Campinas', 'state' => 'SP', 'slug' => 'moto-nova', 'plan_id' => $plan['id'], 'owner_name' => 'Fulano',
            'email' => 'fulano@motonova.test', 'password' => 'uma-senha-longa', 'password_confirmation' => 'uma-senha-longa', 'accept_terms' => '1'];
        $this->assertRedirectTo('/painel', $br->post('/cadastro', $data));
        $t = $this->db->fetch("SELECT * FROM tenants WHERE slug = 'moto-nova'");
        $this->assertSame('trial', $t['status']);
        $this->assertSame('11444777000161', $t['document']);
        $this->assertSame('owner', $this->db->value('SELECT role FROM tenant_users WHERE tenant_id = ?', [$t['id']]));
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM shipping_methods WHERE tenant_id = ?', [$t['id']]));
        $this->assertContains('MODELO', (string) $this->db->value('SELECT policy_returns FROM store_settings WHERE tenant_id = ?', [$t['id']]));
        $this->assertStatus(200, $br->get('/painel'));
        $this->assertStatus(200, $this->browser()->get('/loja/moto-nova/'));
    }

    public function testSignupValidation(): void
    {
        $plan = $this->plan();
        $br = $this->browser();
        $base = ['trade_name' => 'X', 'legal_name' => 'X Ltda', 'document' => '11.111.111/1111-11', 'phone' => '11977776666', 'city' => 'A', 'state' => 'SP',
            'slug' => 'admin', 'plan_id' => $plan['id'], 'owner_name' => 'F', 'email' => $this->t['owner']['email'], 'password' => 'curta', 'password_confirmation' => 'curta', 'accept_terms' => '1'];
        $br->get('/cadastro');
        $br->post('/cadastro', $base, true, ['referer' => 'http://localhost/cadastro']);
        $this->assertTrue(isset($br->session['_errors']['document']), 'CNPJ inválido');
        $this->assertTrue(isset($br->session['_errors']['password']), 'senha curta');
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM tenants'));
        $base['document'] = '11444777000161';
        $base['password'] = $base['password_confirmation'] = 'senha-bem-longa';
        $br->post('/cadastro', $base, true, ['referer' => 'http://localhost/cadastro']);
        $this->assertSame('Este endereço é reservado. Escolha outro.', $br->session['_errors']['slug'] ?? null);
        $base['slug'] = 'auth';
        $br->post('/cadastro', $base, true, ['referer' => 'http://localhost/cadastro']);
        $this->assertSame('Este endereço já está em uso.', $br->session['_errors']['slug'] ?? null);
        $this->assertNotContains('senha-bem-longa', json_encode($br->session['_old'] ?? []), 'senha não volta para o formulário');
    }

    public function testSecurityHeadersArePresent(): void
    {
        $r = $this->browser()->get('/entrar');
        $this->assertContains("script-src 'self'", $r->headers['Content-Security-Policy']);
        $this->assertSame('DENY', $r->headers['X-Frame-Options']);
        $this->assertSame('nosniff', $r->headers['X-Content-Type-Options']);
    }

    public function testOutputIsEscaped(): void
    {
        $tid = (int) $this->t['tenant']['id'];
        (new \App\Services\ProductService($this->db, $tid))->create(['sku' => 'XSS-1', 'name' => '<script>alert(1)</script>', 'price_cents' => '10,00', 'active' => '1']);
        $html = $this->browser()->get('/loja/auth/')->body;
        $this->assertNotContains('<script>alert(1)</script>', $html);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
