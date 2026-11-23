<?php
namespace App\Actions\Programs;

use App\Models\Service;
use Illuminate\Support\Facades\Schema;

class GetServicesNavMenuAction
{
    /**
     * Group all active DB services into structured navigation categories for the Navbar.
     */
    public function execute(): array
    {
        $with = [
            'courseCatalogs' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('title'),
        ];

        if (Schema::hasColumn('services', 'parent_service_id')) {
            $with['childServices'] = fn ($query) => $query->where('is_active', true)->orderBy('menu_order')->orderBy('title');
        }

        $query = Service::active()->with($with)
            ->orderBy('menu_category')
            ->orderBy('menu_order')
            ->orderBy('title');

        if (Schema::hasColumn('services', 'parent_service_id')) {
            $query->whereNull('parent_service_id');
        }

        $services = $query->get(['id', 'title', 'slug', 'icon', 'menu_category', 'menu_order']);

        $categories = [
            'bipa' => [
                'label' => 'Program BIPA',
                'description' => 'Bahasa Indonesia bagi Penutur Asing',
                'items' => [],
            ],
            'kursus_bahasa' => [
                'label' => 'Kursus Bahasa',
                'description' => 'Kursus Inggris, non Inggris, tes penempatan, dan informasi pendaftaran',
                'items' => [],
            ],
            'language_tests' => [
                'label' => 'Tes Bahasa',
                'description' => 'Layanan tes kemampuan bahasa',
                'items' => [],
            ],
            'ppp' => [
                'label' => 'PPP Penerjemahan',
                'description' => 'Penerjemahan Teks & Penjurubahasaan',
                'items' => [],
            ],
        ];

        $languageItems = [
            'english' => [],
            'non_english' => [],
            'test_preparation' => [],
            'registration_info' => [],
        ];
        $testItems = [];
        $pppItems = [
            'services' => [],
            'courses' => [],
            'registration_info' => [],
        ];

        foreach ($services as $service) {
            foreach ($service->courseCatalogs->groupBy('division') as $division => $courses) {
                $items = $courses->map(fn ($course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'href' => "/courses/catalog/{$course->slug}",
                    'service_slug' => $service->slug,
                ])->values()->all();

                $category = match ($division) {
                    'bipa' => 'bipa',
                    'ppb_english' => 'kursus_bahasa',
                    'ppb', 'ppb_foreign', 'testing' => 'kursus_bahasa',
                    'ppp' => 'ppp',
                    default => $service->menu_category ?: 'ppb_foreign',
                };

                $languageTestItems = $courses->where('category', 'language_test')->map(fn ($course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'href' => "/courses/catalog/{$course->slug}",
                    'service_slug' => $service->slug,
                ])->values()->all();

                $testPreparationItems = $courses->where('category', 'test_preparation')->map(fn ($course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'href' => "/courses/catalog/{$course->slug}",
                    'service_slug' => $service->slug,
                    'sort_order' => $course->sort_order,
                ])->values()->all();

                $registrationItems = $courses->where('category', 'registration_info')->map(fn ($course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'href' => "/courses/catalog/{$course->slug}",
                    'service_slug' => $service->slug,
                ])->values()->all();

                $languageTestIds = collect($languageTestItems)->pluck('id');
                $testPreparationIds = collect($testPreparationItems)->pluck('id');
                $registrationIds = collect($registrationItems)->pluck('id');
                $items = array_values(array_filter($items, fn ($item) =>
                    !$languageTestIds->contains($item['id'])
                    && !$testPreparationIds->contains($item['id'])
                    && !$registrationIds->contains($item['id'])
                ));

                $languageItems['test_preparation'] = array_merge(
                    $languageItems['test_preparation'],
                    $testPreparationItems
                );

                if ($division === 'testing') {
                    $testItems = array_merge($testItems, $languageTestItems);
                    continue;
                }

                if ($division === 'ppp') {
                    $pppItems['courses'] = array_merge($pppItems['courses'], $items);
                    $pppItems['registration_info'] = array_merge($pppItems['registration_info'], $registrationItems);
                    continue;
                }

                if ($registrationItems) {
                    $languageItems['registration_info'] = array_merge($languageItems['registration_info'], $registrationItems);
                }
                if ($division === 'ppb_english') {
                    $languageItems['english'] = array_merge($languageItems['english'], $items);
                } elseif (in_array($division, ['ppb', 'ppb_foreign'], true)) {
                    $languageItems['non_english'] = array_merge($languageItems['non_english'], $items);
                } elseif (isset($categories[$category])) {
                    $categories[$category]['items'] = array_merge($categories[$category]['items'], $items);
                }
            }

            if ($service->menu_category === 'bipa') {
                $bipaChildren = [];
                if (Schema::hasColumn('services', 'parent_service_id')) {
                    $bipaChildren = $service->childServices->map(fn ($child) => [
                        'id' => $child->id,
                        'title' => $child->title,
                        'slug' => $child->slug,
                        'href' => "/services/{$child->slug}",
                    ])->values()->all();
                }

                $categories['bipa']['items'] = [
                    [
                        'id' => 'bipa-courses',
                        'title' => 'Courses',
                        'children' => $service->courseCatalogs->map(fn ($course) => [
                            'id' => $course->id,
                            'title' => $course->title,
                            'slug' => $course->slug,
                            'href' => "/courses/catalog/{$course->slug}",
                        ])->values()->all(),
                    ],
                    [
                        'id' => 'bipa-services',
                        'title' => 'Services',
                        'children' => $bipaChildren,
                    ],
                ];
                continue;
            }

            if ($service->courseCatalogs->isEmpty()) {
                $category = match ($service->menu_category) {
                    'ppb_english', 'ppb', 'ppb_foreign' => 'kursus_bahasa',
                    default => $service->menu_category ?: 'ppp',
                };
                if (!isset($categories[$category])) {
                    continue;
                }
                $categories[$category]['items'][] = [
                    'id' => $service->id,
                    'title' => $service->title,
                    'slug' => $service->slug,
                    'icon' => $service->icon,
                    'href' => "/services/{$service->slug}",
                ];
            }

            if ($service->menu_category === 'ppp') {
                $children = Schema::hasColumn('services', 'parent_service_id')
                    ? $service->childServices
                    : collect();

                if ($children->isNotEmpty()) {
                    foreach ($children as $child) {
                        $pppItems['services'][] = [
                            'id' => $child->id,
                            'title' => $child->title,
                            'slug' => $child->slug,
                            'href' => "/services/{$child->slug}",
                        ];
                    }
                } else {
                    $pppItems['services'][] = [
                        'id' => $service->id,
                        'title' => $service->title,
                        'slug' => $service->slug,
                        'icon' => $service->icon,
                        'href' => "/services/{$service->slug}",
                    ];
                }
            }
        }

        $ppbServices = [];
        $ppbService = Service::active()->where('slug', 'program-pelayanan-bahasa-127')->first(['id', 'title', 'slug']);
        if ($ppbService) {
            $ppbServices[] = [
                'id' => $ppbService->id,
                'title' => $ppbService->title,
                'slug' => $ppbService->slug,
                'href' => "/services/{$ppbService->slug}",
            ];
        }

        $placementTestItems = collect($languageItems['test_preparation'])
            ->sortBy('sort_order')
            ->map(function (array $item): array {
                unset($item['sort_order']);

                return $item;
            })
            ->values()
            ->all();

        $categories['kursus_bahasa']['items'] = array_values(array_filter([
            !empty($languageItems['english']) ? [
                'id' => 'english',
                'title' => 'Kursus Inggris',
                'children' => $languageItems['english'],
            ] : null,
            !empty($languageItems['non_english']) ? [
                'id' => 'non-english',
                'title' => 'Kursus Non Inggris',
                'children' => $languageItems['non_english'],
            ] : null,
            !empty($placementTestItems) ? [
                'id' => 'placement-test',
                'title' => 'Tes Penempatan',
                'children' => $placementTestItems,
            ] : null,
            !empty($languageItems['registration_info']) ? [
                'id' => 'registration-info',
                'title' => 'Informasi Pendaftaran',
                'children' => $languageItems['registration_info'],
            ] : null,
        ]));

        $categories['language_tests']['items'] = array_merge($testItems, [[
            'id' => 'language-test-registration-info',
            'title' => 'Informasi Pendaftaran',
            'href' => '/informasi-pendaftaran-tes',
        ]]);

        $categories['ppp']['items'] = array_values(array_filter([
            [
                'id' => 'ppp-services',
                'title' => 'Layanan',
                'children' => $pppItems['services'],
            ],
            [
                'id' => 'ppp-courses',
                'title' => 'Kursus',
                'children' => $pppItems['courses'],
            ],
            [
                'id' => 'ppp-registration-info',
                'title' => 'Informasi Pendaftaran',
                'children' => $pppItems['registration_info'],
            ],
        ]));

        // Return as indexed array for easy frontend consumption
        return array_values(array_filter($categories, fn($cat) => !empty($cat['items'])));
    }
}
