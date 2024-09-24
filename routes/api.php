<?php

use App\Http\Controllers\LatestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::controller(UserController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('get_user_info', 'get_personal_data');
    Route::post('update_password_user', 'update_password');
    Route::post('update_photo_user', 'update_photo');
    Route::post('update_details', 'update_details');

    Route::post('show_all_users', 'showAll');
    Route::get('show_notifications', 'show_notifications');
    Route::get('read_notifications', 'read_notifications');
});
Route::controller(UserController::class)->group(function () {
    Route::post('add_admin', 'add_admin');

    Route::delete('users', 'delete_user');
    Route::get('take_student','take_student');
    Route::get('leave_student','leave_student');
    Route::post('add_wanting_students', 'add_wanting_students');
    Route::post('get_score','get_score');
    Route::get('get_user_by_id','get_user_by_id');
    Route::get('show_users_without_teacher','show_users_without_teacher');
});



Route::controller(LatestController::class)->group(function () {
    Route::post('add_latest_quraan/{user_id}', 'add_latest_quraan');
    Route::post('add_latest_hadith/{user_id}', 'add_latest_hadith');
    Route::post('add_latest_activity/{user_id}', 'add_latest_activity');
    Route::post('add_latest_note/{user_id}', 'add_latest_note');
    Route::get('get_latest_for_student','get_latest_for_student');
    Route::get('get_rank_my_group','get_rank_my_group');
    Route::get('get_rank_masjed','get_rank_masjed');
});

Route::controller(ReportController::class)->group(function () {
    Route::get('show_reports', 'show_reports');
    Route::get('show_user_reports', 'show_user_reports');
});

Route::controller(TestController::class)->group(function () {
    Route::post('add_new_test', 'add_new_test');
    Route::get('show_tests', 'show_tests');
    Route::get('accept_test/{test_id}', 'accept_test');
    Route::delete('delete_accepted_test/{test_id}', 'delete_accepted_test');
    Route::delete('delete_test/{test_id}', 'delete_test');
    Route::get('show_test_accepters/{test_id}', 'show_test_accepters');
    Route::post('update_test_accepter_data/{test_id}/{user_id}', 'update_test_accepter_data');
    Route::get('show_success_students_in_test/{test_id}', 'show_success_students_in_test');
    Route::post('make_aukaf_test_for_success_students/{test_id}', 'make_aukaf_test_for_success_students');
    Route::post('update_aukaf_tests_after_the_test/{test_id}/{user_id}', 'update_aukaf_tests_after_the_test');


});


