<?php

use App\Http\Controllers\DaoraController;
use App\Http\Controllers\FcmController;
use App\Http\Controllers\LatestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityCategoryController;
use App\Http\Controllers\PrayerProgramController;
use App\Http\Controllers\PrayerLogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentGoalController;
use App\Http\Controllers\ArchiveController;
// 1. Update the user's FCM token
Route::post('/update-fcm-token', [FcmController::class, 'updateToken']);

// 2. Get all notifications for the logged-in user
Route::get('/notifications', [FcmController::class, 'getNotifications']);

// 3. Mark all unread notifications as read
Route::post('/notifications/mark-as-read', [FcmController::class, 'markAllAsRead']);

use App\Http\Controllers\SystemController;
use App\Http\Controllers\AdController;

Route::get('app-version', [SystemController::class, 'appVersion']);

Route::get('active-ad', [AdController::class, 'getActiveAd']);
Route::get('ads', [AdController::class, 'index']);
Route::post('ads', [AdController::class, 'store']);
Route::delete('ads/{id}', [AdController::class, 'destroy']);
Route::post('ads/update-stats/{id}', [AdController::class, 'updateStats']);
Route::post('ads/toggle-status/{id}', [AdController::class, 'toggleStatus']);


Route::controller(UserController::class)->group(function () {
    Route::post('register', 'registerStudent');
    Route::post('login', 'login');
    Route::post('get_user_info', 'get_personal_data');
    Route::post('update_password_user', 'update_password');
    Route::post('update_photo_user', 'update_photo');
    Route::post('update_details', 'update_details');
    Route::get('suggestions', 'getSuggestions');
    Route::post('show_all_teachers', 'show_all_teachers');
    Route::get('show_notifications', 'show_notifications');
    Route::get('read_notifications', 'read_notifications');
    Route::post('update_user/{id}', 'updateUser');
});
Route::controller(UserController::class)->group(function () {
    Route::post('add_admin', 'add_admin');

    Route::delete('users', 'delete_user');
    Route::get('take_student', 'take_student');
    Route::get('leave_student', 'leave_student');
    Route::post('add_wanting_students', 'add_wanting_students');
    Route::post('add_absent_teachers', 'add_absent_teachers');
    Route::post('save_absence_reason', 'save_absence_reason');
    Route::post('get_score', 'get_score');
    Route::get('get_user_by_id', 'get_user_by_id');
    Route::get('show_users_without_teacher', 'show_users_without_teacher');
    // === الراوتات المفقودة التي تم استنتاجها من الكود ===
    Route::post('update_user_behavior/{user_id}', 'update_user_behavior');
    Route::get('get_user_behavior/{user_id}', 'get_user_behavior');
    Route::get('student/{studentUserId}/teachers-history', 'getStudentTeachersHistory');
// راوت تحديث صورة الطالب (تم استنتاجه من فلاتر)
    Route::post('update_student_photo/{student_id}', 'update_student_photo');
    Route::put('/users/{user}/toggle-privilege', 'toggleTeacherPrivilege');
});

Route::controller(LatestController::class)->group(function () {
    Route::post('add_latest_quraan/{user_id}', 'add_latest_quraan');
    // ✅ (الراوتات الجديدة)
    Route::post('delete_latest_hadith/{user_id}', 'delete_latest_hadith');
    Route::post('delete_latest_activity/{user_id}', 'delete_latest_activity');
    Route::post('delete_latest_note/{user_id}', 'delete_latest_note');
    Route::post('delete_latest_quraan/{user_id}', 'delete_latest_quraan');
    Route::post('add_latest_hadith/{user_id}', 'add_latest_hadith');
    Route::post('add_latest_activity/{user_id}', 'add_latest_activity');
    Route::post('add_latest_note/{user_id}', 'add_latest_note');
    Route::get('get_latest_for_student', 'get_latest_for_student');
    Route::get('get_rank_my_group', 'get_rank_my_group');
    Route::get('get_rank_masjed', 'get_rank_masjed');
    Route::post('update_halaka_name', 'update_halaka_name');
    Route::post('change_halaka_teacher', 'change_halaka_teacher');
    Route::delete('delete_halaka/{id}', 'delete_halaka');
});

Route::controller(ReportController::class)->group(function () {
    Route::get('show_reports', 'show_reports');
    // Route::get('show_user_reports', 'show_user_reports');
    Route::post('comprehensive_report/{user_id}', 'getComprehensiveReport');
});

Route::controller(TestController::class)->group(function () {
    Route::post('add_new_test', 'add_new_test');
    Route::get('show_tests', 'show_tests');
    Route::get('accept_test/{test_id}', 'accept_test');
    Route::post('accept_test_for_student/{test_id}', 'accept_test_for_student');
    Route::delete('delete_accepted_test/{test_id}', 'delete_accepted_test');
    Route::delete('delete_accepted_test_for_student/{test_id}', 'delete_accepted_test_for_student');
    Route::delete('delete_test/{test_id}', 'delete_test');
    Route::get('show_test_accepters/{test_id}', 'show_test_accepters');
    Route::post('update_test_accepter_data/{test_id}/{user_id}', 'update_test_accepter_data');
    Route::get('show_success_students_in_test/{test_id}', 'show_success_students_in_test');
    Route::post('make_aukaf_test_for_success_students/{test_id}', 'make_aukaf_test_for_success_students');
    Route::post('update_aukaf_tests_after_the_test/{test_id}/{user_id}', 'update_aukaf_tests_after_the_test');
});

Route::controller(DaoraController::class)->group(function () {
    Route::post('add_daora', 'add_daora');
    Route::delete('delete_daora/{id}', 'delete_daora');
    Route::get('get_all_daoras', 'get_all_daoras');

    Route::post('change_my_daora', 'change_my_daora');
    Route::post('/daoras/photos', 'getPhotos');

    Route::get('/jobs-and-areas', 'getJobsAndAreas');
});

// Activity Categories
Route::controller(ActivityCategoryController::class)->group(function () {
    Route::get('activity-categories', 'index');
    Route::post('activity-categories', 'store');
    Route::put('activity-categories/{id}', 'update');
    Route::delete('activity-categories/{id}', 'destroy');
});

// Prayer Programs
Route::controller(PrayerProgramController::class)->group(function () {
    Route::get('prayer-programs', 'index');
    Route::post('prayer-programs', 'store');
    Route::delete('prayer-programs/{id}', 'destroy');
});

// Prayer Logs
Route::controller(PrayerLogController::class)->group(function () {
    Route::get('prayer-programs/{programId}/rankings', 'getRankings');
    Route::get('prayer-programs/{programId}/my-logs', 'getMyLogs');
    Route::get('prayer-programs/{programId}/student-logs/{studentId}', 'getStudentLogs');
    Route::post('prayer-programs/{programId}/logs', 'upsertLog');
});

// Tournaments
Route::controller(\App\Http\Controllers\TournamentController::class)->group(function () {
    Route::get('tournaments', 'index');
    Route::post('tournaments', 'store');
    Route::post('tournaments/{id}/subscribe', 'subscribe');
    Route::post('tournaments/{id}/outcome', 'reportOutcome');
    Route::get('tournaments/{id}', 'show');
});

// Chess Matches
Route::controller(\App\Http\Controllers\ChessMatchController::class)->group(function () {
    Route::post('chess-matches/challenge', 'challenge');
    Route::post('chess-matches/{id}/accept', 'accept');
    Route::post('chess-matches/{id}/decline', 'decline');
    Route::post('chess-matches/{id}/move', 'move');
    Route::post('chess-matches/{id}/end', 'end');
});

// ==========================================
// 1. أهداف الطالب (Student Goals)
// ==========================================
Route::controller(StudentGoalController::class)->group(function () {
    Route::post('add_student_goals', 'add_student_goals');
    Route::get('get_student_goals/{user_id}', 'get_student_goals');
    Route::post('evaluate_goal/{id}', 'evaluate_goal');
    Route::delete('delete_goal/{id}', 'delete_goal');
});

// ==========================================
// 2. نظام الأرشفة (Archive System)
// ==========================================
Route::controller(ArchiveController::class)->group(function () {
    Route::post('archive-daora', 'archiveDaora');
    Route::get('archived-dates/{daoraId}', 'getArchivedDates');
    Route::get('archived-records-by-date/{daoraId}/{date}', 'getArchivedRecordsByDate');
    Route::get('archived-search/{name}', 'searchArchives');
    Route::get('export-students/{daoraId}', 'exportStudentsCSV');
});

// ==========================================
// 3. إدارة التقارير (Management Reports)
// ==========================================
use App\Http\Controllers\ManagementReportController;

Route::controller(ManagementReportController::class)->group(function () {
    // جلب الإحصائيات المحسوبة آلياً
    Route::get('management-reports/teacher-stats/{teacher_id}', 'getTeacherStats');
    Route::get('management-reports/mosque-stats/{daora_id}', 'getMosqueStats');

    // حفظ / مشاركة تقرير
    Route::post('management-reports', 'store');

    // تصفح التقارير
    Route::get('management-reports/my-reports', 'myReports');
    Route::get('management-reports/by-teacher/{teacher_id}', 'byTeacher');
    Route::get('management-reports/by-daora/{daora_id}', 'byDaora');
    Route::get('management-reports/{id}', 'show');

    // طلبات التقارير
    Route::post('report-requests', 'createRequest');
    Route::get('report-requests/pending', 'pendingRequests');

    // أوقات الدورة والمهام اليومية للأساتذة
    Route::post('daora-times', 'saveDaoraTimes');
    Route::get('daora-times/{daora_id}', 'getDaoraTimes');
    Route::post('teacher-daily-tasks/mark-copied', 'markReportCopied');
});

use App\Http\Controllers\DaoraPeriodController;

Route::controller(DaoraPeriodController::class)->group(function () {
    Route::get('daora-periods/{daora_id}', 'index');
    Route::get('daora-periods/details/{id}', 'show');
    Route::post('daora-periods', 'store');
    Route::put('daora-periods/{id}', 'update');
    Route::delete('daora-periods/{id}', 'destroy');
    Route::post('daora-periods/{id}/teachers', 'assignTeachers');
    Route::delete('daora-periods/{id}/teachers/{teacher_id}', 'removeTeacher');
    Route::get('daora-periods/{id}/available-teachers', 'availableTeachers');
});


