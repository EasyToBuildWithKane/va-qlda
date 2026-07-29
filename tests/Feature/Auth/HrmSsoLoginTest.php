<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\SystemAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

/**
 * SSO HRM → Workspace: /auth/hrm redirect sang HRM authorize; callback verify JWT
 * RS256 qua JWKS (offline) rồi mở session guard `system`. Không Google trong luồng.
 */
class HrmSsoLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Key fixture cho test — openssl_pkey_new cần openssl.cnf (thiếu trên
     * Windows dev) nên dùng PEM tĩnh, chỉ phục vụ ký JWT giả lập HRM.
     */
    private const PRIVATE_KEY_PEM = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDeINH4OtTiKE0z
VZPHgdjqWDnc4WE3Adx7yunPBrI/g+y7WqUGRPtimOkuj3D4xoNrQ2n75V+uR16Q
7OYirzv5De7gO40XKwzL/Np9FJvOznhxXpnEVzLF2eShEQS/u6GDuJVAqonPdazx
A5S3zbWb1IxMxUXF1lFJMWy7Nk2uJRzUly53UOuFmyHtOKS6MlgWU+C1r4aLgX9u
7YA2Eo7trPF9dBeHx7NS8x9C9lq3azb7kmN80OvD8QBnh4MBsAGX3C7dt62N2cam
FGQsPId26Rh8ZoYdHWR5Ha981YLZ8P0sj3P1KTxpuq4MyZ5g4GcudTHgz/2f4R4P
0JyrZAJlAgMBAAECggEAKCqZpVAoHiQo/5seL6GmgovFHNChdmOvBbCV0mKU2WDm
7iMQO7++EGBzrAByrn2hFoSYdd0OjfJoYn8AB24WA212UFRcaT/W3s0giiUvnngd
9ewkFImrC/VgfxKXf/8zD+FRFeIwrNPL28IoworfZ+gJnbk/amycgsZ98aV6av6D
QZznqz1JUyYNq39pSnYfbvGMrZGP0kdMIX9RFXomeanw8w3kN9Nt0EK/KJOb46L5
IjEEYOZJtmYi01aFebwmN79eFx7jVZZzaXuIERAw7H70yxIBrrb3o0DI9VRfzigK
nHqWdy0m2/mmXzUS2JbsdHTncjuu5vzkFnqOIldKKQKBgQD0P+32MWEQsRrgDcEK
ZS+/EAlS9W0EPCbe9ogHyo6zRb+FsURbGba/MZnXlrW85RpMrhlhLBo5mEaBVeHo
j57uVcdQfM7+LJeCjXBI7tkLfTQmjE7kVOVPotQHGGrhEHrpYH0tZ6XhfkPwAxkh
X7EkiSP7TZYOyvsvLzuBEGOHiQKBgQDo0HOvF4gIvx4eL2+i46OnX017yrL+OPjF
jfj2NSCAKMY/sRnaTQMQ4OG0LDpMc8iDqv1sJTQgKJdK8D4g2QTorswULFdFqmWn
zqI/YL7bDBZaY24EsWNOB222XcRIZa3x9QkJWp8j1Ph11hKU9S3KWif1+gstiH2u
IMky2fqQ/QKBgQCGKiOtSzx1LcOEdSmnayOKAOYOQq/KVrxHVwYruXEfOl3UsyXI
INzIMxe9W5SimPoUSsG4JcCWCHYaDzHTTCjR5B5isNwGtDnZRZX7CfABJpoh3opr
U9LOJrPu3dO/owzQ6uZ3rWpp7bClwx3nvieQ2SABp4GYyJinN3upDnaNeQKBgDut
SyoDjyQm0TKNmEEZttZtUHA96hnOQr5pAPsjbRxM11x2KhlSCkomStSjTaJRP4G4
r9MryivAG9/iqxpCZsa6H6fAIfLCleIozmAOjX2aoJQ0znu3eKwErXPEn06reaX+
2H0kVIybx8B0yjiSjFVFPN8JZBoc1ZJv5wL46wiFAoGBAOHPQoDqtqFJ7wWz0ppf
5Mh/KLbj89VgQ8Pwdky73/iCYYU2SG0DHbQcn/NKp4CqGsh/4VrFoG0Mr2KLZ0P/
1sk6Q/tQ/l2nCpbZYHWOCoMfIqLZSCbgauFsXJJa19KIulIsL5BsHiieBM1b0sa4
wU/J1EmkmYqMz0iA9bFPH+bI
-----END PRIVATE KEY-----
PEM;

    private OpenSSLAsymmetricKey $privateKey;

    private string $publicKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_get_private(self::PRIVATE_KEY_PEM);
        $this->assertNotFalse($key, 'Không đọc được key fixture.');
        $this->privateKey = $key;
        $this->publicKeyPem = openssl_pkey_get_details($key)['key'];

        config([
            'services.hrm_sso.enabled' => true,
            'services.hrm_sso.base_url' => 'https://hrm.test',
            'services.hrm_sso.client_id' => 'workspace',
            'services.hrm_sso.audience' => 'workspace',
            'services.hrm_sso.issuer' => 'https://hrm.test',
            'services.hrm_sso.jwks_url' => null,
        ]);

        Http::fake([
            'https://hrm.test/.well-known/jwks.json' => Http::response($this->jwks()),
        ]);
    }

    public function test_redirect_sends_user_to_hrm_authorize_with_state(): void
    {
        $response = $this->get('/auth/hrm');

        $location = $response->headers->get('Location');
        $state = session('login.hrm_state');

        $this->assertNotEmpty($state);
        $this->assertStringStartsWith('https://hrm.test/sso/authorize?', (string) $location);

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        $this->assertSame('workspace', $query['client_id']);
        $this->assertSame(route('auth.hrm.callback'), $query['redirect_uri']);
        $this->assertSame($state, $query['state']);
    }

    public function test_redirect_flashes_error_when_sso_disabled(): void
    {
        config(['services.hrm_sso.enabled' => false]);

        $this->get('/auth/hrm')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_callback_logs_in_employee_and_links_hrm_employee_uuid(): void
    {
        $employee = Employee::factory()->create(['email' => 'user@vaschools.edu.vn']);
        $uuid = (string) Str::uuid();

        $jwt = $this->signJwt(['email' => 'user@vaschools.edu.vn', 'employee_uuid' => $uuid]);

        $response = $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 'state-123'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $jwt, 'state' => 'state-123']));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::guard('system')->check());
        $this->assertSame($uuid, $employee->fresh()->hrm_employee_uuid);

        $account = SystemAccount::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($account);
        $this->assertTrue($account->is_active);
    }

    public function test_callback_matches_employee_by_previously_linked_uuid(): void
    {
        $uuid = (string) Str::uuid();
        $employee = Employee::factory()->create([
            'email' => 'old-email@vaschools.edu.vn',
            'hrm_employee_uuid' => $uuid,
        ]);

        // Email trên HRM đã đổi — vẫn nhận ra nhân sự qua employee_uuid.
        $jwt = $this->signJwt(['email' => 'new-email@vaschools.edu.vn', 'employee_uuid' => $uuid]);

        $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 's'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $jwt, 'state' => 's']))
            ->assertRedirect('/dashboard');

        $this->assertTrue(Auth::guard('system')->check());
        $this->assertSame(
            $employee->id,
            Auth::guard('system')->user()->employee_id,
        );
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $jwt = $this->signJwt(['email' => 'user@vaschools.edu.vn']);

        $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 'expected'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $jwt, 'state' => 'tampered']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::guard('system')->check());
    }

    public function test_callback_rejects_wrong_audience(): void
    {
        Employee::factory()->create(['email' => 'user@vaschools.edu.vn']);
        $jwt = $this->signJwt(['email' => 'user@vaschools.edu.vn', 'aud' => 'other-app']);

        $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 's'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $jwt, 'state' => 's']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::guard('system')->check());
    }

    public function test_callback_rejects_expired_token(): void
    {
        Employee::factory()->create(['email' => 'user@vaschools.edu.vn']);
        $jwt = $this->signJwt(['email' => 'user@vaschools.edu.vn', 'exp' => time() - 10]);

        $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 's'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $jwt, 'state' => 's']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::guard('system')->check());
    }

    public function test_callback_rejects_tampered_signature(): void
    {
        Employee::factory()->create(['email' => 'user@vaschools.edu.vn']);
        $jwt = $this->signJwt(['email' => 'user@vaschools.edu.vn']);
        $tampered = substr($jwt, 0, -4).'AAAA';

        $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 's'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $tampered, 'state' => 's']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::guard('system')->check());
    }

    public function test_callback_rejects_unknown_email(): void
    {
        // HRM API chưa cấu hình trong test → không lazy upsert được.
        $jwt = $this->signJwt(['email' => 'stranger@vaschools.edu.vn']);

        $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 's'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $jwt, 'state' => 's']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::guard('system')->check());
    }

    public function test_callback_rejects_inactive_employee(): void
    {
        $uuid = (string) Str::uuid();
        Employee::factory()->create([
            'email' => 'user@vaschools.edu.vn',
            'hrm_employee_uuid' => $uuid,
            'is_active' => false,
        ]);

        $jwt = $this->signJwt(['email' => 'user@vaschools.edu.vn', 'employee_uuid' => $uuid]);

        $this->withSession(['login.portal' => 'portal', 'login.hrm_state' => 's'])
            ->get('/auth/hrm/callback?'.http_build_query(['token' => $jwt, 'state' => 's']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::guard('system')->check());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function signJwt(array $overrides = []): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'https://hrm.test',
            'aud' => 'workspace',
            'sub' => (string) Str::uuid(),
            'jti' => (string) Str::uuid(),
            'iat' => $now,
            'exp' => $now + 600,
            'email' => 'user@vaschools.edu.vn',
            'name' => 'Test User',
            'employee_uuid' => null,
            'roles' => ['staff'],
        ], $overrides);

        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->keyId()];

        $signingInput = $this->base64UrlEncode((string) json_encode($header))
            .'.'
            .$this->base64UrlEncode((string) json_encode($claims));

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @return array{keys: list<array<string, string>>}
     */
    private function jwks(): array
    {
        $rsa = openssl_pkey_get_details($this->privateKey)['rsa'];

        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => $this->keyId(),
                    'n' => $this->base64UrlEncode($rsa['n']),
                    'e' => $this->base64UrlEncode($rsa['e']),
                ],
            ],
        ];
    }

    private function keyId(): string
    {
        return substr(hash('sha256', $this->publicKeyPem), 0, 16);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
