<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActionLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('action-logs.view');
        $query = ActionLog::with('actor');

        if ($search = trim((string) $request->q)) {
            $query->where('title', 'like', "%{$search}%");
        }
        if (in_array($request->type, ['Created', 'Updated', 'Deleted'], true)) {
            $query->where('type', $request->type);
        }

        $sort = in_array($request->sort, ['type', 'title', 'action_by', 'created_at'], true) ? $request->sort : 'created_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        return view('action-logs.index', [
            'logs' => $query->orderBy($sort, $direction)->paginate(config('app.table_page_length'))->withQueryString(),
        ]);
    }
}
