<?php

namespace Modules\Notes\Http\Controllers;

use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Notes\Entities\Note;
use Yajra\DataTables\Html\Builder;

class NotesController extends Controller
{
    use UserSessionAttributes;
    use MakesHash;
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Builder $builder)
    {
        if (request()->ajax()) {

            $notes = Note::query()->where('company_id', '=', $this->getCurrentCompanyId());

            return DataTables::of($notes->get())
                ->addColumn('created_at', function ($note) {
                    return $note->created_at;
                })
                ->addColumn('description', function ($note) {
                    return $note->description;
                })
                ->addColumn('action', function ($client) {
                    return '<a href="/notes/'. $this->encodePrimaryKey($note->id) .'/edit" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i> Edit</a>';
                })
                ->addColumn('checkbox', function ($client){
                    return '<input type="checkbox" name="bulk" value="'. $note->id .'"/>';
                })
                ->rawColumns(['checkbox', 'action'])
                ->make(true);
        }

        $builder->addAction();
        $builder->addCheckbox();
        
        $html = $builder->columns([
            ['data' => 'created_at', 'name' => 'checkbox', 'title' => '', 'searchable' => false, 'orderable' => false],
            ['data' => 'description', 'name' => 'name', 'title' => trans('texts.name'), 'visible'=> true],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'searchable' => false, 'orderable' => false],
        ]);

        $builder->ajax([
            'url' => route('notes.index'),
            'type' => 'GET',
            'data' => 'function(d) { d.key = "value"; }',
        ]);

        $data['html'] = $html;

        return view('notes::index', $data);
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        return view('notes::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Show the specified resource.
     * @return Response
     */
    public function show()
    {
        return view('notes::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @return Response
     */
    public function edit()
    {
        return view('notes::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function update(Request $request)
    {
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy()
    {
    }
}
