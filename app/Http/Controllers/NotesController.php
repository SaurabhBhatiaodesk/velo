<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Models\Notes\StoreRequest;
use App\Http\Requests\Models\Notes\UpdateRequest;
use App\Http\Requests\Models\Notes\DeleteRequest;

class NotesController extends Controller
{
    private function getSlug($slug)
    {
        if (!str_starts_with($slug, 'App\\Models\\')) {
            return 'App\\Models\\' . ucfirst($slug);
        }
        return $slug;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Note  $note
     * @return \Illuminate\Http\Response
     */
    public function forModel($modelSlug, $modelId)
    {
        return Note::where('notable_type', $this->getSlug($modelSlug))->where('notable_id', $modelId)->with('user')->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $note = Note::create([
            'notable_id' => $inputs['notable_id'],
            'notable_type' => $this->getSlug($inputs['notable_type']),
            'note' => $inputs['note'],
            'user_id' => Auth::id(),
        ]);
        if (!$note) {
            return $this->respond(['message' => 'notes.createFailed'], 500);
        }
        return $this->respond($note->load('user'), 201);
    }

    /**
     * Update the specified resource
     *
     * @param  \App\Models\Note  $note
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Note $note)
    {
        if (!$note) {
            return $this->respond(['message' => 'notes.notFound'], 404);
        }
        $inputs = $this->validateRequest($request);
        if (
            !$note->update([
                'notable_id' => $inputs['notable_id'],
                'notable_type' => $this->getSlug($inputs['notable_type']),
                'note' => $inputs['note'],
                'user_id' => Auth::id(),
            ])
        ) {
            return $this->respond(['message' => 'notes.saveFailed'], 500);
        }

        return $this->respond($note);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Note  $note
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteRequest $request, Note $note)
    {
        if (!$note->delete()) {
            return $this->respond(['message' => 'notes.deleteFailed'], 500);
        }
        return $this->respond(['message' => 'notes.deleteSuccess'], 200);
    }
}
