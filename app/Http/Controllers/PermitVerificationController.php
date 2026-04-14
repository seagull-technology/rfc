<?php

namespace App\Http\Controllers;

use App\Models\Permit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermitVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'permit_number' => ['nullable', 'string', 'max:100'],
        ]);

        $permit = null;

        if (filled($filters['permit_number'] ?? null)) {
            $permit = Permit::query()
                ->with(['application.entity', 'issuedBy'])
                ->where('permit_number', trim((string) $filters['permit_number']))
                ->first();
        }

        return view('permits.verify', [
            'filters' => [
                'permit_number' => $filters['permit_number'] ?? '',
            ],
            'permit' => $permit,
            'mode' => 'lookup',
        ]);
    }

    public function showSigned(Request $request, Permit $permit): View
    {
        return view('permits.verify', [
            'filters' => [
                'permit_number' => $permit->permit_number,
            ],
            'permit' => $permit->load(['application.entity', 'issuedBy']),
            'mode' => 'signed',
        ]);
    }
}
