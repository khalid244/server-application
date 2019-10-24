<?php

namespace App\Http\Controllers\Api\v1\Statistic;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Project;
use DB;
use Validator;
use Carbon\Carbon;

class ProjectReportController extends Controller
{
    /**
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'start_at' => 'date',
            'end_at' => 'date',
        ];
    }

    /**
     * @return array
     */
    public static function getControllerRules(): array
    {
        return [
            'report' => 'project-report.list',
            'projects' => 'project-report.projects',
            'task' => 'project-report.list',
            'days' => 'time-duration.list',
        ];
    }

    /**
     * [report description]
     * @param Request $request [description]
     * @return [type]           [description]
     */
    public function report(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            $this->getValidationRules()
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    'error' => 'Validation fail',
                    'reason' => $validator->errors()
                ], 400
            );
        }

        $uids = $request->input('uids');
        $pids = $request->input('pids');

        $user = auth()->user();
        $timezone = $user->timezone ?: 'UTC';
        $timezoneOffset = (new Carbon())->setTimezone($timezone)->format('P');

        $startAt = Carbon::parse($request->input('start_at'), $timezone)
            ->tz('UTC')
            ->toDateTimeString();

        $endAt = Carbon::parse($request->input('end_at'), $timezone)
            ->tz('UTC')
            ->toDateTimeString();

        $projectReports = DB::table('project_report')
            ->select('user_id', 'user_name', 'task_id', 'project_id', 'task_name', 'project_name',
                DB::raw("DATE(CONVERT_TZ(date, '+00:00', '{$timezoneOffset}')) as date"),
                DB::raw('SUM(duration) as duration')
            )
            ->whereIn('user_id', $uids)
            ->whereIn('project_id', $pids)
            ->whereIn('project_id', Project::getUserRelatedProjectIds($user))
            ->where('date', '>=', $startAt)
            ->where('date', '<', $endAt)
            ->groupBy('user_id', 'user_name', 'task_id', 'project_id', 'task_name', 'project_name')
            ->get();

        $projects = [];

        foreach ($projectReports as $projectReport) {
            $project_id = $projectReport->project_id;
            $user_id = $projectReport->user_id;

            if (!isset($projects[$project_id])) {
                $projects[$project_id] = [
                    'id' => $project_id,
                    'name' => $projectReport->project_name,
                    'users' => [],
                    'project_time' => 0,
                ];
            }

            if (!isset($projects[$project_id]['users'][$user_id])) {
                $projects[$project_id]['users'][$user_id] = [
                    'id' => $user_id,
                    'full_name' => $projectReport->user_name,
                    'tasks' => [],
                    'tasks_time' => 0,
                ];
            }


            $projects[$project_id]['users'][$user_id]['tasks'][] = [
                'id' => $projectReport->task_id,
                'project_id' => $projectReport->project_id,
                'user_id' => $projectReport->user_id,
                'task_name' => $projectReport->task_name,
                'duration' => (int)$projectReport->duration,
            ];

            $projects[$project_id]['users'][$user_id]['tasks_time'] += $projectReport->duration;
            $projects[$project_id]['project_time'] += $projectReport->duration;
        }


        foreach ($projects as $project_id => $project) {
            $projects[$project_id]['users'] = array_values($project['users']);
        }

        $projects = array_values($projects);

        return $projects;
    }

    /**
     * [events description]
     * @param Request $request [description]
     * @return \Illuminate\Http\JsonResponse [description]
     */
    public function days(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            $this->getValidationRules()
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    'error' => 'Validation fail',
                    'reason' => $validator->errors()
                ], 400
            );
        }

        $uids = $request->uids;

        $user = auth()->user();
        $timezone = $user->timezone ?: 'UTC';
        $timezoneOffset = (new Carbon())->setTimezone($timezone)->format('P');

        $startAt = Carbon::parse($request->input('start_at'), $timezone)
            ->tz('UTC')
            ->toDateTimeString();

        $endAt = Carbon::parse($request->input('end_at'), $timezone)
            ->tz('UTC')
            ->toDateTimeString();

        $days = DB::table('project_report')
            ->select('user_id', 'date',
                DB::raw("DATE(CONVERT_TZ(date, '+00:00', '{$timezoneOffset}')) as date"),
                DB::raw('SUM(duration) as duration')
            )
            ->whereIn('project_id', Project::getUserRelatedProjectIds(Auth::user()))
            ->where('date', '>=', $startAt)
            ->where('date', '<', $endAt)
            ->groupBy('user_id', 'date');

        if (!empty($uids)) {
            $days->whereIn('user_id', $uids);
        }

        return response()->json($days->get());
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function projects(Request $request)
    {
        $uids = $request->uids;
        // Get projects, where specified users is attached.
        $users_attached_project_ids = Project::whereHas('users', function ($query) use ($uids) {
            $query->whereIn('id', $uids);
        })->pluck('id');

        // Get projects, where specified users have intervals.
        $users_related_project_ids = Project::whereHas('tasks.timeIntervals', function ($query) use ($uids) {
            $query->whereIn('user_id', $uids);
        })->pluck('id');

        $project_ids = collect([$users_attached_project_ids, $users_related_project_ids])->collapse()->unique();

        // Get projects, directly attached to the current user.
        $attached_project_ids = Project::whereHas('users', function ($query) use ($uids) {
            $query->where('id', Auth::user()->id);
        })->pluck('id');

        // Filter projects by directly attached to the current user, if have attached.
        if ($attached_project_ids->count() > 0) {
            $project_ids = $project_ids->intersect($attached_project_ids);
        }

        // Load projects.
        $projects = Project::query()->whereIn('id', $project_ids)->get(['id', 'name']);

        return response()->json($projects);
    }

    /**
     * Returns durations per date for a task.
     *
     * @param $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function task($id, Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            $this->getValidationRules()
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    'error' => 'Validation fail',
                    'reason' => $validator->errors()
                ], 400
            );
        }

        $uid = $request->uid;

        $user = auth()->user();
        $timezone = $user->timezone ?: 'UTC';
        $timezoneOffset = (new Carbon())->setTimezone($timezone)->format('P'); # Format +00:00

        $startAt = Carbon::parse($request->input('start_at'), $timezone)
            ->tz('UTC')
            ->toDateTimeString();

        $endAt = Carbon::parse($request->input('end_at'), $timezone)
            ->tz('UTC')
            ->toDateTimeString();

        $report = DB::table('project_report')
            ->select(
                DB::raw("DATE(CONVERT_TZ(date, '+00:00', '{$timezoneOffset}')) as date"),
                DB::raw('SUM(duration) as duration')
            )
            ->where('task_id', $id)
            ->where('user_id', $uid)
            ->where('date', '>=', $startAt)
            ->where('date', '<', $endAt)
            ->get(['date', 'duration']);

        return response()->json($report);
    }
}
