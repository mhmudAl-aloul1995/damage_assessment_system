<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDamageAssessmentReadOnly
{
    /** @param \Closure(\Illuminate\Http\Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole('UNDP')) {
            return $next($request);
        }

        abort_unless($request->isMethodSafe(), 403);
        abort_if($this->isRestrictedReadRoute($request), 403);

        $requiredPermission = $this->requiredPermission($request);
        abort_unless($requiredPermission !== null && $user->can($requiredPermission), 403);

        return $next($request);
    }

    private function isRestrictedReadRoute(Request $request): bool
    {
        $routeName = mb_strtolower((string) $request->route()?->getName());
        $actionMethod = (string) $request->route()?->getActionMethod();
        $restrictedWords = 'create|edit|export|download|import|sync|store|update|delete|destroy|approve|reject|reset|cancel|process|sign|assign|retry';

        return $actionMethod === 'gitPush'
            || preg_match('/(^|\.)('.$restrictedWords.')([._-]|$)/', $routeName) === 1
            || preg_match('/^('.$restrictedWords.')([A-Z_].*)?$/', $actionMethod) === 1;
    }

    private function requiredPermission(Request $request): ?string
    {
        $path = mb_strtolower($request->path());

        return match (true) {
            str_contains($path, '/inf-audit/public-buildings') => 'inf-audit.public-buildings.view',
            str_contains($path, '/inf-audit/roads') => 'inf-audit.roads.view',
            str_contains($path, '/inf-audit/cso') => 'inf-audit.cso.view',
            str_contains($path, '/cso-surveys') && str_contains($path, '/reports/area-productivity') => 'reports.area-productivity.cso-surveys.view',
            str_contains($path, '/cso-surveys') => 'cso-surveys.view',
            str_contains($path, '/committee-members') => 'committee-members.view',
            str_contains($path, '/committee-decisions'), str_contains($path, '/committee-archive') => 'committee-decisions.view',
            str_contains($path, '/reports/damage-statistics') => 'reports.damage-statistics.view',
            str_contains($path, '/reports/building-productivity'), str_contains($path, '/reports/productivity') => 'reports.productivity.view',
            str_contains($path, '/reports/area-productivity') => 'reports.area-productivity.view',
            str_contains($path, '/reports/field-engineer') => 'reports.field-engineer.view',
            str_contains($path, '/reports/daily-achievement'), str_contains($path, '/reports/auditors-daily'), str_contains($path, '/reports/lawyers-daily') => 'reports.daily-achievement.view',
            str_contains($path, '/reports/hlp-audit') => 'reports.hlp-audit.view',
            str_contains($path, '/reports/public-buildings') => 'reports.public-buildings.view',
            str_contains($path, '/reports/road-facilities') => 'reports.road-facilities.view',
            str_contains($path, '/reports/') => 'reports.view',
            str_contains($path, '/attendance/monthly-report'), str_contains($path, '/attendance/summary') => 'attendance.reports.view',
            str_contains($path, '/attendance') => 'attendance.view',
            str_contains($path, '/building-survey-return-requests') => 'building-survey-return-requests.view',
            str_contains($path, '/building-deletions') => 'damage-assessment.building-deletion.view',
            str_contains($path, '/audit'), str_contains($path, '/area-manager-review'), str_contains($path, '/assessment-edit-histories'), str_contains($path, '/assessment/inline-history'), str_contains($path, '/field-engineer-audit'), str_contains($path, '/lawyer-table'), str_contains($path, '/showassessmentaudit') => 'audit.view',
            str_contains($path, '/public-buildings') => 'inf-audit.public-buildings.view',
            str_contains($path, '/road-facilities') => 'inf-audit.roads.view',
            str_contains($path, '/housing'), str_contains($path, '/showhousing') => 'housing-units.view',
            str_contains($path, '/building'), str_contains($path, '/showbuildings'), str_contains($path, '/assessment'), str_contains($path, '/damageassessment'), str_contains($path, '/engineer'), str_contains($path, '/sectors/'), $path === 'damage-assessment', str_ends_with($path, '/damage-assessment') => 'damage-assessments.view',
            str_contains($path, '/api/get-latest-stats'), str_contains($path, '/search-buildings'), str_contains($path, '/global-search') => 'damage-assessments.view',
            default => null,
        };
    }
}
