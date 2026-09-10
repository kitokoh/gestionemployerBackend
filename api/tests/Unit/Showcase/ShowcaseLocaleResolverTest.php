<?php

declare(strict_types=1);

namespace Tests\Unit\Showcase;

use App\Modules\Showcase\Infrastructure\Services\ShowcaseLocaleResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * BC-27 SHOWCASE (#6874 V-I18N) — résolution de la locale publique :
 * sélecteur `?lang=` prioritaire, puis `Accept-Language` (q-values), repli
 * `fr`, toute valeur inconnue ignorée.
 */
final class ShowcaseLocaleResolverTest extends TestCase
{
    private ShowcaseLocaleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ShowcaseLocaleResolver;
    }

    private function request(array $query = [], string $acceptLanguage = ''): Request
    {
        $server = $acceptLanguage !== '' ? ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage] : [];

        return Request::create('/api/v1/public/vitrine/acme', 'GET', $query, [], [], $server);
    }

    public function test_explicit_lang_wins(): void
    {
        $this->assertSame('ar', $this->resolver->resolve($this->request(['lang' => 'ar'], 'en-GB,en;q=0.9')));
        $this->assertSame('tr', $this->resolver->resolve($this->request(['lang' => 'tr-TR'])));
        $this->assertSame('en', $this->resolver->resolve($this->request(['lang' => 'EN'])));
    }

    public function test_unknown_lang_falls_back_to_accept_language(): void
    {
        $this->assertSame('en', $this->resolver->resolve($this->request(['lang' => 'de'], 'en')));
        // Langue non supportée ignorée → repli fr.
        $this->assertSame('fr', $this->resolver->resolve($this->request(['lang' => 'de'], 'de-DE,de;q=0.9')));
    }

    public function test_accept_language_quality_order(): void
    {
        $this->assertSame('ar', $this->resolver->resolve($this->request([], 'fr;q=0.4,ar;q=0.9,en;q=0.5')));
        // À qualité égale, la première déclarée gagne.
        $this->assertSame('tr', $this->resolver->resolve($this->request([], 'tr,en;q=0.8')));
        // Qualité nulle ignorée.
        $this->assertSame('fr', $this->resolver->resolve($this->request([], 'ar;q=0')));
    }

    public function test_default_is_french(): void
    {
        $this->assertSame('fr', $this->resolver->resolve($this->request()));
        $this->assertSame('fr', $this->resolver->resolve($this->request([], 'zh-CN,zh;q=0.9')));
        $this->assertSame('fr', $this->resolver->resolve($this->request([], '*')));
    }
}
