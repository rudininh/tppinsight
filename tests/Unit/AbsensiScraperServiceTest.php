<?php

namespace Tests\Unit;

use App\Services\AbsensiScraperService;
use PHPUnit\Framework\TestCase;

class AbsensiScraperServiceTest extends TestCase
{
    public function test_extract_skpd_login_action_prefers_login_link_over_other_actions(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeExtractSkpdLoginAction(string $html, int $skpdId): array
            {
                return $this->extractSkpdLoginAction($html, $skpdId);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>1.01.01.</td>
                        <td>Dinas Pendidikan</td>
                        <td>Y</td>
                        <td>
                            <a href="/superadmin/skpd/reset/10">Reset Pass</a>
                            <a href="/superadmin/skpd/detail/10">Detail</a>
                            <a href="/superadmin/skpd/login/10">Login</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $action = $service->exposeExtractSkpdLoginAction($html, 1);

        $this->assertSame('GET', $action['method']);
        $this->assertSame('/superadmin/skpd/login/10', $action['url']);
        $this->assertSame('login_link', $action['source']);
    }

    public function test_extract_skpd_login_action_prefers_login_form_over_other_forms(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeExtractSkpdLoginAction(string $html, int $skpdId): array
            {
                return $this->extractSkpdLoginAction($html, $skpdId);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>1.01.01.</td>
                        <td>Dinas Pendidikan</td>
                        <td>Y</td>
                        <td>
                            <form method="POST" action="/superadmin/skpd/reset/10">
                                <input type="hidden" name="_token" value="abc">
                                <button type="submit">Reset Pass</button>
                            </form>
                            <form method="POST" action="/superadmin/skpd/login/10">
                                <input type="hidden" name="_token" value="abc">
                                <button type="submit" name="login" value="1">Login</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $action = $service->exposeExtractSkpdLoginAction($html, 1);

        $this->assertSame('POST', $action['method']);
        $this->assertSame('/superadmin/skpd/login/10', $action['url']);
        $this->assertSame('login_form', $action['source']);
        $this->assertSame(['_token' => 'abc', 'login' => '1'], $action['form_params']);
    }

    public function test_skpd_listing_page_is_not_treated_as_cuti_data(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeIsSkpdListingPage(string $html): bool
            {
                return $this->isSkpdListingPage($html);
            }
        };

        $html = <<<'HTML'
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode SKPD</th>
                        <th>Nama SKPD</th>
                        <th>WFH</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>1.01.01.</td>
                        <td>Dinas Pendidikan</td>
                        <td>Y</td>
                        <td>Reset Pass Detail Login</td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $this->assertTrue($service->exposeIsSkpdListingPage($html));
    }
}
