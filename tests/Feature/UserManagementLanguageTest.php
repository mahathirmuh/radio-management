<?php

namespace Tests\Feature;

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * User Management in the language of the page: Indonesian until another one is chosen (UserManagementTest checks the
 * pages and their messages in English, the language they were written in), the words of its scripts, and what the server
 * answers.
 */
class UserManagementLanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;
    use UsesEnglish;

    protected bool $seed = true;

    private function viewer(string $email): User
    {
        return $this->makeUser($email, 'Viewer Radio');
    }

    public function test_the_page_of_the_users_is_in_indonesian_until_another_language_is_chosen(): void
    {
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('user-management.user-registered.index'))->assertOk()->getContent();

        preg_match_all('#<th scope="col"[^>]*>\s*(?:<span[^>]*>)?([^<]*?)\s*<#', $page, $titles);
        $this->assertSame(['Pilih', 'No', 'Nama', 'Email', 'Role', 'Status', 'Cara masuk', 'Terakhir masuk', 'Aksi'], $titles[1]);
        foreach ([
            '<h1 class="rm-title">User Terdaftar</h1>', 'placeholder="Nama atau email"', '> Tambah user</button>',
            'Batalkan pilihan', '<option value="">Semua status</option>', '<option value="inactive">Nonaktif</option>',
            'Semua role', 'lebih baik nonaktifkan saja', 'Tambah user baru', 'Cara masuk', 'Username direktori',
            'placeholder="Diambil dari email bila dikosongkan"', 'Active Directory belum diatur di server ini',
            'Masuk dengan akun Active Directory-nya.', '<option value="">Pilih role</option>', 'Akun Anda sendiri tetap aktif.',
            '> Simpan perubahan</button>', 'Atur kata sandi', 'Ulangi kata sandi', 'placeholder="Minimal 8 karakter, dengan huruf dan angka"',
            // The words of the scripts.
            '"selected":":count user dipilih"', '"Pending":"Belum dipakai"', '"confirm_title":"Datanya sudah benar?"',
        ] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        // Nothing of the English page is left.
        foreach ([
            'Add user', 'Add new user', 'Clear selection', 'All statuses', 'All roles', 'Sign-in method', 'Set password', 'Last sign-in',
            'Repeat the password', 'Is the data correct?', 'Error loading data', 'Taken from the email if left blank', 'Select role',
            'People who can sign in',
        ] as $english) {
            $this->assertStringNotContainsString($english, $page, $english);
        }
    }

    public function test_the_script_of_the_list_speaks_indonesian(): void
    {
        $this->checkPageScript(route('user-management.user-registered.index'), 'tests/tools/user-language-behaviour.mjs', 5);
    }

    public function test_the_server_answers_in_the_language_of_the_page(): void
    {
        $admin = $this->superAdmin();
        $ani = $this->viewer('ani@merdekabattery.com');
        $bayu = $this->viewer('bayu@merdekabattery.com');
        $this->actingAs($admin);

        // Indonesian: what was done, what was skipped and why, and the names of the fields in the validation.
        $this->putJson(route('user-management.user-registered.bulk-status'), ['ids' => [$ani->id, $bayu->id, $admin->id], 'active' => 0])
            ->assertOk()
            ->assertJsonPath('message', '2 user dinonaktifkan. 1 dilewati.')
            ->assertJsonPath('skipped', [['user' => $admin->email, 'reason' => 'Anda tidak bisa menonaktifkan akun Anda sendiri']]);
        $this->putJson(route('user-management.user-registered.status', $ani->id), ['active' => 1])
            ->assertOk()->assertJsonPath('message', 'User diaktifkan.');
        $this->postJson(route('user-management.user-registered.store'), [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Isian salah!')
            ->assertJsonPath('errors', ['Kolom nama wajib diisi.', 'Kolom email wajib diisi.', 'Kolom role wajib diisi.']);
        $this->postJson(route('user-management.role-permission.store'), [])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['Kolom nama role wajib diisi.', 'Kolom halaman awal wajib diisi.']);

        // English, for whoever chose it.
        $this->useEnglish();
        $this->putJson(route('user-management.user-registered.status', $ani->id), ['active' => 0])
            ->assertOk()->assertJsonPath('message', 'User deactivated.');
        $this->postJson(route('user-management.user-registered.store'), [])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['The name field is required.', 'The email field is required.', 'The role id field is required.']);
    }

    public function test_the_page_of_the_roles_is_in_indonesian_and_names_the_menus_as_the_sidebar_does(): void
    {
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('user-management.role-permission.index'))->assertOk()->getContent();

        preg_match_all('#<th scope="col"[^>]*>\s*([^<]*?)\s*</th>#', $page, $titles);
        $this->assertSame(['No', 'Nama', 'Jumlah user', 'Aksi'], $titles[1]);
        $user = DB::table('menu')->where('route', 'user-management.user-registered.index')->value('id');
        foreach ([
            '<h1 class="rm-title">Role dan hak akses</h1>', 'placeholder="Cari…"', '> Tambah role</button>',
            'Menu &amp; hak akses', 'Halaman awal', '<th class="perm-kind text-center">Tambah</th>', '<th class="perm-kind text-center">Lihat</th>',
            '<th class="perm-kind text-center">Ubah</th>', '<th class="perm-kind text-center">Hapus</th>', 'title="Memberi akses penuh ke sistem"',
            // A menu by its name in the sidebar, not by its title in the table ("User Registered").
            '<option value="' . $user . '">User Terdaftar</option>', 'aria-label="User Terdaftar: akses"', 'aria-label="User Terdaftar: Ubah"',
            // The words of the scripts.
            '"view_title":"Lihat role"', '"delete_title":"Hapus role ini?"', '"noun":"role"',
        ] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        foreach (['Roles and permissions', 'Add Role', 'Total user', 'Home Page', 'Allows a full access', 'User Registered', 'Is the data correct?'] as $english) {
            $this->assertStringNotContainsString($english, $page, $english);
        }
    }
}
