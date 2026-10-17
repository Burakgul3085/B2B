<?php

namespace Tests\Feature\Communication;

use App\Models\Announcement;
use App\Models\Attachment;
use App\Models\DealerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;
use ZipArchive;

/** Duyuru ekleri: PDF/görsel/Word/Excel kabulü, içerik doğrulaması ve bayi bazında indirme yetkisi. */
class AnnouncementAttachmentTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_business_attaches_office_and_pdf_files_and_targeted_dealer_downloads_them(): void
    {
        $business = $this->makeBusiness();
        $inGroup = $this->orderingDealer($business);
        $outside = $this->orderingDealer($business);
        $group = $this->inTenant($business, fn () => DealerGroup::create(['name' => 'Okul bayileri']));
        $inGroup->forceFill(['dealer_group_id' => $group->id])->saveQuietly();

        $this->actingAs($business->user, 'business')->post(route('business.announcements.store'), [
            'title' => 'Yeni fiyat listesi', 'body' => 'Ekte.', 'dealer_group_id' => $group->id,
            'files' => [
                UploadedFile::fake()->createWithContent('fiyat listesi.xlsx', $this->office('xl/workbook.xml')),
                UploadedFile::fake()->createWithContent('sozlesme.docx', $this->office('word/document.xml')),
                UploadedFile::fake()->createWithContent('katalog.pdf', "%PDF-1.4\n%x"),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $files = $this->inTenant($business, fn () => Attachment::where('attachable_type', 'announcement')->orderBy('id')->get());
        $this->assertSame(['fiyat listesi.xlsx', 'sozlesme.docx', 'katalog.pdf'], $files->pluck('original_name')->all());
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $files[0]->mime);
        $announcement = $this->inTenant($business, fn () => Announcement::sole());

        $this->get(route('business.announcements.index'))->assertOk()->assertSee('fiyat listesi.xlsx');
        $this->get(route('business.announcements.attachment', [$announcement, $files[1]]))->assertOk()->assertDownload('sozlesme.docx');

        // Hedefteki bayi duyuruyu ve eki görür, indirir.
        $this->flushSession();
        $this->actingAs($inGroup->dealer->user, 'dealer');
        $this->get(route('dealer.announcements.index'))->assertOk()->assertSee('Yeni fiyat listesi')->assertSee('katalog.pdf');
        $this->get(route('dealer.announcements.attachment', [$announcement, $files[2]]))->assertOk()->assertDownload('katalog.pdf');

        // Grubun dışındaki bayi ne duyuruyu görür ne de eki indirebilir.
        $this->flushSession();
        $this->actingAs($outside->dealer->user, 'dealer');
        $this->get(route('dealer.announcements.index'))->assertOk()->assertDontSee('Yeni fiyat listesi');
        $this->get(route('dealer.announcements.attachment', [$announcement, $files[2]]))->assertNotFound();
    }

    public function test_disguised_and_macro_enabled_files_are_rejected_and_nothing_is_published(): void
    {
        $business = $this->makeBusiness();
        $this->orderingDealer($business);
        $this->actingAs($business->user, 'business');

        $cases = [
            'yeniden adlandırılmış zip' => UploadedFile::fake()->createWithContent('rapor.xlsx', $this->office('baska/dosya.txt')),
            'makrolu Excel' => UploadedFile::fake()->createWithContent('makro.xlsx', $this->office('xl/workbook.xml', macro: true)),
            'çalıştırılabilir dosya' => UploadedFile::fake()->createWithContent('kurulum.docx', "MZ\x90\x00".str_repeat("\x00", 64)),
        ];

        foreach ($cases as $label => $file) {
            $this->post(route('business.announcements.store'), ['title' => 'T', 'body' => 'B', 'files' => [$file]])
                ->assertSessionHasErrors('files', $label);
        }

        $this->assertSame(0, $this->inTenant($business, fn () => Announcement::count()));
        $this->assertSame(0, $this->inTenant($business, fn () => Attachment::count()));
    }

    public function test_attachment_of_another_announcement_cannot_be_fetched_through_a_visible_one(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);

        [$visible, $hiddenFile] = $this->inTenant($business, function () use ($business) {
            $group = DealerGroup::create(['name' => 'Başka grup']);
            $visible = Announcement::create(['title' => 'Herkese', 'body' => 'B', 'published_at' => now(), 'created_by' => $business->user->id]);
            $hidden = Announcement::create(['title' => 'Gruba', 'body' => 'B', 'dealer_group_id' => $group->id, 'published_at' => now(), 'created_by' => $business->user->id]);
            Storage::disk('local')->put('gizli.pdf', '%PDF-1.4');
            $file = Attachment::create([
                'attachable_type' => 'announcement', 'attachable_id' => $hidden->id, 'stored_path' => 'gizli.pdf', 'original_name' => 'gizli.pdf',
                'mime' => 'application/pdf', 'size' => 8, 'sha256' => str_repeat('a', 64),
            ]);

            return [$visible, $file];
        });

        $this->actingAs($rel->dealer->user, 'dealer')
            ->get(route('dealer.announcements.attachment', [$visible, $hiddenFile]))->assertNotFound();
    }

    /** Asgari bir Office paketi (OOXML) üretir. */
    private function office(string $part, bool $macro = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ooxml');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $type = $macro ? 'application/vnd.ms-excel.sheet.macroEnabled.main+xml' : 'application/xml';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="'.$type.'"/></Types>');
        $zip->addFromString($part, '<?xml version="1.0"?><root/>');
        $zip->close();
        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }
}
