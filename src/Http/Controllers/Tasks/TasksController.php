<?php

namespace Lockminds\Teams\Http\Controllers\Tasks;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Lockminds\Teams\Http\Controllers\MyController;
use Lockminds\Teams\Models\LockmindsTeams;
use Lockminds\Teams\Models\LockmindsTeamMembers;
use Lockminds\Teams\Models\LockmindsTeamTasks;
use Lockminds\Teams\Teams;


class TasksController extends MyController
{

    protected $teamModel,$teamMembersModel,$teamTasksModel;

    public function __construct()
    {
        $this->teamModel = new LockmindsTeams();
        $this->teamMembersModel = new LockmindsTeamMembers();
        $this->teamTasksModel =  new LockmindsTeamTasks();
    }

    public function index(Request $request)
    {
        $this->pageData['page_title'] = "Tasks";
        $this->setUser($request);

        return view('teams::pages.tasks.dashboard')->with($this->pageData);
    }

    public function create(Request $request)
    {

        if ($request->isMethod('post')) {

            if ($request->hasFile('team_icon')) {
                if ($request->file('team_icon')->isValid()) {
                    if($icon = $this->uploadTeamIcon($request)){
                        $this->teamModel->team_icon = $icon;
                    }
                }
            }

            $this->teamModel->team_owner = Auth::user()->id;
            $this->teamModel->team_name = $request->team_name;
            $this->teamModel->team_description = $request->team_description;
            if($status = $this->teamModel->save()){
                $this->pageData['action_status'] = true;
                $this->pageData['action_message'] = "You have successfully created a TEAM ".$request->team_name;
                return view('teams::pages.team.team_create')->with($this->pageData);
            }else{
                $this->pageData['action_status'] = false;
                $this->pageData['action_message'] = $this->teamModel->errors();
            }
        }

        return view('teams::pages.team.team_create')->with($this->pageData);
    }

    public function details(Request $request, $id){

        $this->setUser($request);

        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        if ($request->isMethod('post')) {

            $allmembers = [];
            foreach($request->members as $item){
                $model = new LockmindsTeamMembers();
                $model->team_member_owner = Auth::user()->id;
                $model->team_member_id = $item;
                $model->team_id = $id;
                $model->team_member_enabled = true;
                $model->team_welcome_message = $request->welcome_message;
                $allmembers[] = $model->attributesToArray();
            }

            $status = LockmindsTeamMembers::insert($allmembers);

            if($status){
                $this->pageData['action_status'] = true;
                $this->pageData['action_message'] = "You have successfully added ".count($allmembers).' member(s)';
            }else{
                $this->pageData['action_status'] = false;
                $this->pageData['action_message'] = $this->teamModel->errors();
            }
        }

        $team = $this->teamModel::find($id);
        $this->pageData['members'] = $this->teamMembersModel::where("team_id",$id)->leftJoin('users', 'lockminds_team_members.team_member_id', '=', 'users.id')->get();
        $this->pageData['team'] = $team;

        $team = $this->teamModel::find($id);
        $this->pageData['team'] = $team;
        $this->pageData['page_description'] = "Details | ".strtoupper($team['team_name']);
        return view('teams::pages.team.team_details')->with($this->pageData);
    }

    public function members(Request $request, $id){

        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        $this->setUser($request);

        if ($request->isMethod('post')) {

            $allmembers = [];
            foreach($request->members as $item){
                $model = new LockmindsTeamMembers();
                $model->team_member_owner = Auth::user()->id;
                $model->team_member_id = $item;
                $model->team_id = $id;
                $model->team_member_enabled = true;
                $model->team_welcome_message = $request->welcome_message;
                $allmembers[] = $model->attributesToArray();
            }

            $status = LockmindsTeamMembers::insert($allmembers);

            if($status){
                $this->pageData['action_status'] = true;
                $this->pageData['action_message'] = "You have successfully added ".count($allmembers).' member(s)';
            }else{
                $this->pageData['action_status'] = false;
                $this->pageData['action_message'] = $this->teamModel->errors();
            }
        }

        $team = $this->teamModel::find($id);
        $this->pageData['members'] = $this->teamMembersModel::where("team_id",$id)->leftJoin('users', 'lockminds_team_members.team_member_id', '=', 'users.id')->get();
        $this->pageData['team'] = $team;
        $this->pageData['page_description'] = "Details | ".strtoupper($team['team_name']);
        return view('teams::pages.team.team_details_members')->with($this->pageData);
    }

    public function tasks(Request $request, $id){
        $this->setUser($request);

        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        $team = $this->teamModel::find($id);

        $this->pageData['team'] = $team;
        $this->pageData['active_link'] = "all";
        $this->pageData['members'] = $this->teamMembersModel::where("team_id",$id)->leftJoin('users', 'lockminds_team_members.team_member_id', '=', 'users.id')->get();
        $this->pageData['tasks'] = $this->teamTasksModel::where("task_team",$id)->get();
        $this->pageData['page_description'] = "Details | ".strtoupper($team['team_name']);
        $this->createtask($request,$id);
        return view('teams::pages.team.team_details_tasks')->with($this->pageData);
    }

    public function newtasks(Request $request){
        $this->pageData['page_title'] = "New Tasks";
        $this->setUser($request);
        return view('teams::pages.tasks.newtasks')->with($this->pageData);
    }

    public function progress(Request $request){
        $this->pageData['page_title'] = "Tasks on progress";
        $this->setUser($request);
        return view('teams::pages.tasks.progresstasks')->with($this->pageData);
    }

    public function finished(Request $request){
        $this->pageData['page_title'] = "Tasks on progress";
        $this->setUser($request);
        return view('teams::pages.tasks.completestasks')->with($this->pageData);
    }
    public function mytasks(Request $request, $id){
        $this->setUser($request);


        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        $team = $this->teamModel::find($id);

        $this->createtask($request,$id);
        $this->pageData['active_link'] = "mytasks";
        $this->pageData['team'] = $team;
        $this->pageData['members'] = $this->teamMembersModel::where("team_id",$id)->leftJoin('users', 'lockminds_team_members.team_member_id', '=', 'users.id')->get();
        $this->pageData['tasks'] = $this->teamTasksModel::where("task_team",$id)->orWhere("task_responsible",Auth::user()->id)->orWhere("task_creator",Auth::user()->id)->get();
        $this->pageData['page_description'] = "Details | ".strtoupper($team['team_name']);
        return view('teams::pages.team.team_details_tasks')->with($this->pageData);
    }

    public function chattingroom(Request $request, $id,$member){
        $this->setUser($request);

        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        $this->setUser($request);

        $team = $this->teamModel::find($id);
        $this->pageData['members'] = $this->teamMembersModel::where("team_id",$id)->leftJoin('users', 'lockminds_team_members.team_member_id', '=', 'users.id')->get();
        $this->pageData['team'] = $team;
        $this->pageData['member'] = User::find($member);
        $this->pageData['page_description'] = "Details | ".strtoupper($team['team_name']);
        return view('teams::pages.team.team_details_chattingroom')->with($this->pageData);
    }

    public function chatroom(Request $request, $id){

        $this->setUser($request);

        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        $this->setUser($request);
        $team = $this->teamModel::find($id);

        $this->pageData['team'] = $team;
        $this->pageData['page_description'] = "Details | ".strtoupper($team['team_name']);
        return view('teams::pages.team.team_details_chatroom')->with($this->pageData);
    }

    public function videoroom(Request $request, $id){
        $this->setUser($request);

        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        $team = $this->teamModel::find($id);
        $this->pageData['team'] = $team;
        $this->pageData['page_description'] = "Details | ".strtoupper($team['team_name']);
        return view('teams::pages.team.team_details_videoroom')->with($this->pageData);
    }

    private function createtask(Request $request, $id){
        $this->setUser($request);

        if(!Teams::isTeaMember($id,$request->user()->id))
            abort(404);

        if($request->isMethod("post")){
            $this->teamTasksModel->task_creator = Auth::user()->id;
            $this->teamTasksModel->task_title = $request->task_title;
            $this->teamTasksModel->task_description = $request->task_description;
            $this->teamTasksModel->task_status = $request->task_status;
            $this->teamTasksModel->task_responsible = $request->task_responsible;
            $this->teamTasksModel->task_team = $id;
            $this->teamTasksModel->task_start = date("Y-m-d H:i:s", strtotime($request->task_start));
            $this->teamTasksModel->task_end = date("Y-m-d H:i:s", strtotime($request->task_end));

            $status = $this->teamTasksModel->save();
            if($status){
                $this->pageData['action_status'] = true;
                $this->pageData['action_message'] = "You have successfully created task";
                return redirect()->route('stuff.show', ['id' => $id])->withInput();
            }else{
                $this->pageData['action_status'] = false;
                $this->pageData['action_message'] = $this->teamModel->errors();
            }
        }
    }

    public function uploadTeamIcon(Request $request){

        $time = Carbon::now();
        // Requesting the file from the form
        $icon = $request->file('team_icon');
        // Getting the extension of the file
        $extension = $icon->getClientOriginalExtension();
        // Creating the directory, for example, if the date = 18/10/2017, the directory will be 2017/10/
        $directory = date_format($time, 'Y') . '/' . date_format($time, 'm');
        // Creating the file name: random string followed by the day, random number and the hour
        $filename = random_int(0,5).date_format($time,'d').rand(1,9).date_format($time,'h').".".$extension;
        // This is our upload main function, storing the image in the storage that named 'public'
        $upload_success = $icon->storeAs($directory, $filename, 'public');
        // If the upload is successful, return the name of directory/filename of the upload.

        if ($upload_success) {
            $icon->move(public_path(config("taskmanager.uploads_folder")),$filename);
            return $filename;
        }

        return false;

    }

    public function deletetask(Request $request, $task=""){
        if( empty($task)){
            $output['status'] = false;
            $output['message'] = "We could not perform operation";
        }else{
            $delete =  LockmindsTeamTasks::find($task);
            if($delete->delete()){
                $output['status'] = true;
                $output['message'] = "You have successfully deleted task";
            }else{
                $output['status'] = true;
                $output['message'] = $delete->errors();
            }

        }

        print \GuzzleHttp\json_encode($output,JSON_PRETTY_PRINT);

    }


}
