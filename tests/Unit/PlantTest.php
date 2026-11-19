<?php

namespace Tests\Unit;

use App\Http\Controllers\PlantsController;
use App\Services\PlantPhoto;
use Tests\TestCase;

class PlantTest extends TestCase
{
    public function test_progress_is_bounded_and_handles_missing_or_invalid_dates(): void
    {
        $this->travelTo(now()->startOfDay());
        $previous = now()->subDays(3)->valueOf();
        $controller = new PlantsController();

        $this->assertSame(50, $controller->getPercentageOfCareDate($previous, now()->addDays(3)->valueOf()));
        $this->assertSame(100, $controller->getPercentageOfCareDate($previous, now()->subDay()->valueOf()));
        $this->assertSame(0, $controller->getPercentageOfCareDate($previous, $previous));
        $this->assertSame(0, $controller->getPercentageOfCareDate(null, null));
        $this->assertSame(0, $controller->getPercentageOfCareDate(now()->addDay()->valueOf(), now()->addDays(2)->valueOf()));
    }

    public function test_webcam_photo_requires_matching_image_bytes(): void
    {
        $png = base64_encode(file_get_contents(public_path('images/plant.png')));

        $this->assertTrue(PlantPhoto::isValidWebcamImage('data:image/png;base64,'.$png));
        $this->assertFalse(PlantPhoto::isValidWebcamImage('data:image/jpeg;base64,'.$png));
        $this->assertFalse(PlantPhoto::isValidWebcamImage('data:image/png;base64,'.base64_encode('text')));
    }
}
