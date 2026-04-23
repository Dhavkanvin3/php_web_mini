<?php

namespace App\Http\Controllers;

use App\Models\GenerationHistory;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WebsiteController extends Controller
{
    // ──────────────────────────────────────────────
    // MOCKED AI CONTENT GENERATOR
    // ──────────────────────────────────────────────
    private function generateContent(string $businessName, string $businessType, string $description): array
    {
        $type = strtolower($businessType);

        if (str_contains($type, 'gym') || str_contains($type, 'fitness')) {
            return [
                'title'    => "{$businessName} | Transform Your Body",
                'tagline'  => 'Push Your Limits. Build Your Legacy.',
                'about'    => "{$businessName} is a world-class fitness center dedicated to helping you achieve peak performance. {$description} Our expert trainers and state-of-the-art equipment ensure every workout counts.",
                'services' => [
                    'Personal Training',
                    'Group Fitness Classes',
                    'Nutrition Counseling',
                    'Strength & Conditioning',
                    'Yoga & Mindfulness',
                    'Online Fitness Programs',
                ],
            ];
        }

        if (str_contains($type, 'restaurant') || str_contains($type, 'food') || str_contains($type, 'cafe')) {
            return [
                'title'    => "{$businessName} | A Culinary Experience",
                'tagline'  => 'Every Bite Tells a Story.',
                'about'    => "{$businessName} is a passionate culinary destination where flavors come alive. {$description} We source the finest ingredients to craft dishes that delight and inspire.",
                'services' => [
                    'Dine-In Experience',
                    'Takeaway & Delivery',
                    'Private Event Catering',
                    'Chef\'s Tasting Menu',
                    'Corporate Lunch Packages',
                    'Cooking Classes',
                ],
            ];
        }

        if (str_contains($type, 'tech') || str_contains($type, 'software') || str_contains($type, 'it')) {
            return [
                'title'    => "{$businessName} | Innovate. Build. Scale.",
                'tagline'  => 'Engineering the Future, One Line at a Time.',
                'about'    => "{$businessName} is a cutting-edge technology company at the forefront of digital innovation. {$description} We build robust, scalable solutions that drive business growth.",
                'services' => [
                    'Custom Software Development',
                    'Cloud Infrastructure',
                    'Mobile App Development',
                    'UI/UX Design',
                    'DevOps & CI/CD',
                    'IT Consulting',
                ],
            ];
        }

        if (str_contains($type, 'salon') || str_contains($type, 'beauty') || str_contains($type, 'spa')) {
            return [
                'title'    => "{$businessName} | Beauty Redefined",
                'tagline'  => 'Feel Beautiful. Feel You.',
                'about'    => "{$businessName} is a premier beauty and wellness destination. {$description} Our skilled professionals use only premium products to help you look and feel your absolute best.",
                'services' => [
                    'Haircut & Styling',
                    'Skin Care Treatments',
                    'Manicure & Pedicure',
                    'Facial & Cleansing',
                    'Bridal Packages',
                    'Relaxation Massage',
                ],
            ];
        }

        // Generic fallback
        return [
            'title'    => "{$businessName} | Excellence Delivered",
            'tagline'  => 'Your Vision, Our Commitment.',
            'about'    => "{$businessName} is a leading provider of professional services. {$description} We are committed to delivering exceptional quality and outstanding results for every client.",
            'services' => [
                'Professional Consultation',
                'Custom Solutions',
                'Project Management',
                'Quality Assurance',
                'Ongoing Support',
                'Strategic Planning',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    // POST /api/generate
    // ──────────────────────────────────────────────
    public function generate(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|min:3|max:255',
            'business_type' => 'required|string|max:255',
            'description'   => 'required|string|max:2000',
        ]);

        $user = $request->user();

        // ── Daily limit check (5 per user per day) ──
        $todayCount = GenerationHistory::where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($todayCount >= 5) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Daily generation limit reached. You can generate up to 5 websites per day.',
            ], 429);
        }

        // ── Generate content ──
        $content = $this->generateContent(
            $request->business_name,
            $request->business_type,
            $request->description
        );

        $prompt = [
            'business_name' => $request->business_name,
            'business_type' => $request->business_type,
            'description'   => $request->description,
        ];

        // ── Save to websites table ──
        $website = Website::create([
            'user_id'       => $user->id,
            'business_name' => $request->business_name,
            'business_type' => $request->business_type,
            'description'   => $request->description,
            'title'         => $content['title'],
            'tagline'       => $content['tagline'],
            'about'         => $content['about'],
            'services'      => $content['services'],
        ]);

        // ── Save generation history ──
        GenerationHistory::create([
            'user_id'  => $user->id,
            'prompt'   => $prompt,
            'response' => $content,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Website content generated successfully.',
            'data'    => [
                'website'          => $website,
                'remaining_today'  => 4 - $todayCount,
            ],
        ], 201);
    }

    // ──────────────────────────────────────────────
    // GET /api/websites
    // ──────────────────────────────────────────────
    public function index(Request $request)
    {
        $websites = Website::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'status'  => 'success',
            'message' => 'Websites retrieved successfully.',
            'data'    => [
                'websites'   => $websites->items(),
                'pagination' => [
                    'current_page'  => $websites->currentPage(),
                    'last_page'     => $websites->lastPage(),
                    'per_page'      => $websites->perPage(),
                    'total'         => $websites->total(),
                    'next_page_url' => $websites->nextPageUrl(),
                    'prev_page_url' => $websites->previousPageUrl(),
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    // GET /api/websites/{id}
    // ──────────────────────────────────────────────
    public function show(Request $request, $id)
    {
        $website = Website::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $website) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Website not found.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Website retrieved successfully.',
            'data'    => ['website' => $website],
        ]);
    }

    // ──────────────────────────────────────────────
    // PUT /api/websites/{id}
    // ──────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $website = Website::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $website) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Website not found.',
            ], 404);
        }

        $request->validate([
            'business_name' => 'sometimes|string|min:3|max:255',
            'business_type' => 'sometimes|string|max:255',
            'description'   => 'sometimes|string|max:2000',
            'title'         => 'sometimes|string|max:255',
            'tagline'       => 'sometimes|string|max:255',
            'about'         => 'sometimes|string',
            'services'      => 'sometimes|array',
            'services.*'    => 'string',
        ]);

        $website->update($request->only([
            'business_name',
            'business_type',
            'description',
            'title',
            'tagline',
            'about',
            'services',
        ]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Website updated successfully.',
            'data'    => ['website' => $website->fresh()],
        ]);
    }

    // ──────────────────────────────────────────────
    // DELETE /api/websites/{id}
    // ──────────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $website = Website::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $website) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Website not found.',
            ], 404);
        }

        $website->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Website deleted successfully.',
            'data'    => null,
        ]);
    }

    // ──────────────────────────────────────────────
    // GET /api/history
    // ──────────────────────────────────────────────
    public function history(Request $request)
    {
        $history = GenerationHistory::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'status'  => 'success',
            'message' => 'Generation history retrieved successfully.',
            'data'    => [
                'history'    => $history->items(),
                'pagination' => [
                    'current_page'  => $history->currentPage(),
                    'last_page'     => $history->lastPage(),
                    'per_page'      => $history->perPage(),
                    'total'         => $history->total(),
                    'next_page_url' => $history->nextPageUrl(),
                    'prev_page_url' => $history->previousPageUrl(),
                ],
            ],
        ]);
    }
}