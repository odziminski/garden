<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Models\User;
use Carbon\Carbon;
use Cloudinary\Cloudinary;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;

class PlantPhotoJournalTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $cloudinary;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cloudinary.cloud_url' => 'cloudinary://test-key:test-secret@demo']);
        $this->cloudinary = new MockHandler;
        $handler = HandlerStack::create($this->cloudinary);
        $handler->push(Middleware::history($this->requests));
        Cloudinary::setDefaultHttpClient(new Client(['handler' => $handler]));
    }

    protected function tearDown(): void
    {
        Cloudinary::setDefaultHttpClient(null);

        parent::tearDown();
    }

    public function test_owner_can_upload_a_private_journal_photo_and_view_its_note(): void
    {
        $owner = User::factory()->create();
        $plant = Plant::factory()->for($owner, 'owner')->create();
        $this->fakeSuccessfulUpload();

        $this->actingAs($owner)->post("/plants/{$plant->id}/photo-journal", [
            'taken_on' => '2024-02-29',
            'photo' => UploadedFile::fake()->image('fern.png'),
            'note' => 'New fronds appeared.',
        ])->assertRedirect(route('plants', $plant).'#photo-journal');

        $entry = PlantPhotoEntry::query()->sole();
        $this->assertSame('2024-02-29', $entry->taken_on->toDateString());
        $this->assertSame($plant->id, $entry->plant_id);
        $this->assertSame('png', $entry->format);
        $this->assertSame('authenticated', $this->multipartField($this->requests[0]['request'], 'type'));
        $this->assertSame($entry->public_id, $this->multipartField($this->requests[0]['request'], 'public_id'));

        $this->get(route('plants', $plant))
            ->assertOk()
            ->assertSee('New fronds appeared.')
            ->assertSee(route('plants.photo-journal.show', [$plant, $entry]));

        $image = $this->get(route('plants.photo-journal.show', [$plant, $entry]))->assertRedirect();
        $location = $image->headers->get('Location');
        $this->assertStringStartsWith('https://api.cloudinary.com/v1_1/demo/image/download?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('authenticated', $query['type']);
        $this->assertSame($entry->public_id, $query['public_id']);
        $this->assertGreaterThan(now()->timestamp, (int) $query['expires_at']);
        $this->assertLessThanOrEqual(now()->addMinutes(5)->timestamp, (int) $query['expires_at']);
    }

    public function test_missing_configuration_returns_service_unavailable_for_the_owner_only(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $entry = $this->createEntry($plant);
        config(['cloudinary.cloud_url' => null]);

        $url = route('plants.photo-journal.show', [$plant, $entry]);
        $this->actingAs($owner)->get($url)->assertStatus(503);
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->assertCount(0, $this->requests);
    }

    public function test_journal_is_scoped_to_the_plant_and_orders_entries_by_date(): void
    {
        $plant = Plant::factory()->create();
        $this->createEntry($plant, ['taken_on' => '2024-02-01', 'note' => 'Oldest fern photo']);
        $this->createEntry($plant, ['taken_on' => '2024-03-01', 'note' => 'Newest fern photo']);
        $this->createEntry(Plant::factory()->for($plant->owner, 'owner')->create(), ['note' => 'Other plant photo']);

        $this->actingAs($plant->owner)->get(route('plants', $plant))
            ->assertOk()
            ->assertSeeInOrder(['Newest fern photo', 'Oldest fern photo'])
            ->assertDontSee('Other plant photo');
    }

    public function test_date_field_uses_the_owners_current_local_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19T23:30:00Z'));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $plant = Plant::factory()->for($owner, 'owner')->create();
        $this->fakeSuccessfulUpload();

        $response = $this->actingAs($owner)->get(route('plants', $plant))->assertOk();
        $this->assertMatchesRegularExpression(
            '~<input id="journal-taken-on"[^>]*max="2026-11-20"[^>]*value="2026-11-20"~',
            $response->getContent(),
        );

        $this->post("/plants/{$plant->id}/photo-journal", [
            'taken_on' => '2026-11-20',
            'photo' => UploadedFile::fake()->image('fern.png'),
        ])->assertSessionHasNoErrors();

        $entry = PlantPhotoEntry::query()->sole();
        $this->assertSame('2026-11-20', $entry->taken_on->toDateString());
        $this->assertNull($entry->note);
    }

    public function test_guests_cannot_read_or_change_a_journal(): void
    {
        $plant = Plant::factory()->create();
        $entry = $this->createEntry($plant);
        $journal = "/plants/{$plant->id}/photo-journal";

        $this->get("$journal/{$entry->id}")->assertRedirect(route('login'));
        $this->post($journal)->assertRedirect(route('login'));
        $this->delete("$journal/{$entry->id}")->assertRedirect(route('login'));
        $this->assertCount(0, $this->requests);
    }

    public function test_an_owner_cannot_access_another_users_journal(): void
    {
        $plant = Plant::factory()->create();
        $entry = $this->createEntry($plant);
        $journal = "/plants/{$plant->id}/photo-journal";
        $this->actingAs(User::factory()->create());

        $this->get(route('plants', $plant))->assertNotFound();
        $this->get("$journal/{$entry->id}")->assertNotFound();
        $this->post($journal)->assertNotFound();
        $this->delete("$journal/{$entry->id}")->assertNotFound();
        $this->assertDatabaseHas('plant_photo_entries', ['id' => $entry->id]);
        $this->assertCount(0, $this->requests);
    }

    public function test_photo_cannot_be_read_or_deleted_using_a_different_plant_id(): void
    {
        $owner = User::factory()->create();
        $plant = Plant::factory()->for($owner, 'owner')->create();
        $entry = $this->createEntry(Plant::factory()->for($owner, 'owner')->create());
        $url = "/plants/{$plant->id}/photo-journal/{$entry->id}";

        $this->actingAs($owner)->get($url)->assertNotFound();
        $this->delete($url)->assertNotFound();
        $this->assertDatabaseHas('plant_photo_entries', ['id' => $entry->id]);
        $this->assertCount(0, $this->requests);
    }

    public function test_missing_or_invalid_fields_are_rejected_before_contacting_cloudinary(): void
    {
        $plant = Plant::factory()->create();
        $journal = "/plants/{$plant->id}/photo-journal";
        $this->actingAs($plant->owner);

        $this->post($journal)
            ->assertSessionHasErrorsIn('photoJournal', ['taken_on', 'photo']);

        $this->post($journal, [
            'taken_on' => '2024-02-30',
            'photo' => UploadedFile::fake()->createWithContent('fern.png', '<?php echo "not an image";'),
            'note' => str_repeat('a', 501),
        ])->assertSessionHasErrorsIn('photoJournal', ['taken_on', 'photo', 'note']);

        $this->post($journal, [
            'taken_on' => now()->addDay()->toDateString(),
            'photo' => UploadedFile::fake()->image('fern.png')->size(5121),
        ])->assertSessionHasErrorsIn('photoJournal', ['taken_on', 'photo']);

        $this->assertDatabaseCount('plant_photo_entries', 0);
        $this->assertCount(0, $this->requests);
    }

    public function test_missing_cloudinary_configuration_leaves_no_entry(): void
    {
        config(['cloudinary.cloud_url' => null]);
        $plant = Plant::factory()->create();

        $this->actingAs($plant->owner)->post("/plants/{$plant->id}/photo-journal", [
            'taken_on' => '2024-02-29',
            'photo' => UploadedFile::fake()->image('fern.png'),
        ])->assertSessionHasErrorsIn('photoJournal', 'photo');

        $this->assertDatabaseCount('plant_photo_entries', 0);
        $this->assertCount(0, $this->requests);
    }

    public function test_failed_cloudinary_upload_leaves_no_entry(): void
    {
        $plant = Plant::factory()->create();
        $this->cloudinary->append(
            $this->cloudinaryResponse(['error' => ['message' => 'Upload unavailable']], 503),
            $this->cloudinaryResponse(['result' => 'not found']),
        );

        $this->actingAs($plant->owner)->post("/plants/{$plant->id}/photo-journal", [
            'taken_on' => '2024-02-29',
            'photo' => UploadedFile::fake()->image('fern.png'),
        ])->assertSessionHasErrorsIn('photoJournal', 'photo');

        $this->assertDatabaseCount('plant_photo_entries', 0);
    }

    public function test_a_database_failure_after_upload_removes_the_remote_photo(): void
    {
        $plant = Plant::factory()->create();
        $this->fakeSuccessfulUpload();
        $this->cloudinary->append($this->cloudinaryResponse(['result' => 'ok']));
        PlantPhotoEntry::creating(fn () => throw new RuntimeException('Database write failed'));

        $this->actingAs($plant->owner)->post("/plants/{$plant->id}/photo-journal", [
            'taken_on' => '2024-02-29',
            'photo' => UploadedFile::fake()->image('fern.png'),
        ])->assertServerError();

        $this->assertDatabaseCount('plant_photo_entries', 0);
        $this->assertCount(2, $this->requests);
        $this->assertStringEndsWith('/destroy', $this->requests[1]['request']->getUri()->getPath());
        $this->assertSame(
            $this->multipartField($this->requests[0]['request'], 'public_id'),
            $this->encodedField($this->requests[1]['request'], 'public_id'),
        );
    }

    public function test_owner_can_remove_a_photo_whose_remote_file_is_already_missing(): void
    {
        $plant = Plant::factory()->create();
        $entry = $this->createEntry($plant);
        $this->cloudinary->append($this->cloudinaryResponse(['result' => 'not found']));

        $this->actingAs($plant->owner)->delete("/plants/{$plant->id}/photo-journal/{$entry->id}")
            ->assertRedirect(route('plants', $plant).'#photo-journal');

        $this->assertDatabaseMissing('plant_photo_entries', ['id' => $entry->id]);
        $this->assertSame($entry->public_id, $this->encodedField($this->requests[0]['request'], 'public_id'));
        $this->assertSame('authenticated', $this->encodedField($this->requests[0]['request'], 'type'));
    }

    public function test_failed_remote_deletion_keeps_the_entry_for_retry(): void
    {
        $plant = Plant::factory()->create();
        $entry = $this->createEntry($plant);
        $this->cloudinary->append($this->cloudinaryResponse(['error' => ['message' => 'Delete unavailable']], 503));

        $this->actingAs($plant->owner)->delete("/plants/{$plant->id}/photo-journal/{$entry->id}")
            ->assertSessionHasErrorsIn('photoJournal', 'delete');

        $this->assertDatabaseHas('plant_photo_entries', ['id' => $entry->id]);
    }

    public function test_deleting_a_plant_cleans_up_only_its_journal_assets(): void
    {
        $owner = User::factory()->create();
        $plant = Plant::factory()->for($owner, 'owner')->create();
        $entry = $this->createEntry($plant);
        $otherEntry = $this->createEntry(Plant::factory()->for($owner, 'owner')->create());
        $rowsStillExistAtCleanup = null;
        $this->cloudinary->append(function () use ($plant, $entry, &$rowsStillExistAtCleanup) {
            $rowsStillExistAtCleanup = Plant::query()->whereKey($plant->id)->exists()
                || PlantPhotoEntry::query()->whereKey($entry->id)->exists();

            return $this->cloudinaryResponse(['error' => ['message' => 'Delete unavailable']], 503);
        });

        $this->actingAs($owner)->delete(route('deletePlant', $plant))->assertRedirect(route('browse'));

        $this->assertDatabaseMissing('plants', ['id' => $plant->id]);
        $this->assertDatabaseMissing('plant_photo_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('plant_photo_entries', ['id' => $otherEntry->id]);
        $this->assertFalse($rowsStillExistAtCleanup);
        $this->assertCount(1, $this->requests);
        $this->assertSame($entry->public_id, $this->encodedField($this->requests[0]['request'], 'public_id'));
    }

    private function createEntry(Plant $plant, array $overrides = []): PlantPhotoEntry
    {
        return PlantPhotoEntry::query()->create($overrides + [
            'plant_id' => $plant->id,
            'taken_on' => '2024-02-29',
            'public_id' => 'plant_journal/'.fake()->uuid(),
            'format' => 'png',
        ]);
    }

    private function fakeSuccessfulUpload(): void
    {
        $this->cloudinary->append(fn (RequestInterface $request) => $this->cloudinaryResponse([
            'public_id' => $this->multipartField($request, 'public_id'),
            'format' => 'png',
        ]));
    }

    private function cloudinaryResponse(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private function multipartField(RequestInterface $request, string $name): string
    {
        preg_match('/name="'.preg_quote($name, '/').'"[^\r\n]*\r\n(?:[^\r\n]+\r\n)*\r\n([^\r\n]+)/', (string) $request->getBody(), $matches);

        return $matches[1] ?? '';
    }

    private function encodedField(RequestInterface $request, string $name): string
    {
        parse_str((string) $request->getBody(), $fields);

        return $fields[$name] ?? '';
    }
}
