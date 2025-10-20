<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InquiryController extends Controller
{
    public function index(): JsonResponse
    {
        $inquiries = Inquiry::query()
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $inquiries,
        ]);
    }

    public function store(Request $request): Response
    {
        return response()->noContent();
    }

    public function update(Request $request, Inquiry $inquiry): Response
    {
        return response()->noContent();
    }

    public function destroy(Inquiry $inquiry): Response
    {
        return response()->noContent();
    }
}
