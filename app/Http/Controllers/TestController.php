<?php

namespace App\Http\Controllers;

use App\Services\TestService;
use App\Http\Requests\Api\Test\AddNewTestRequest;
use App\Http\Requests\Api\Test\UpdateAukafTestRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestController extends Controller
{
    protected $testService;

    public function __construct(TestService $testService)
    {
         $this->testService = $testService;
    }

    public function add_new_test(AddNewTestRequest $request)
    {
        try {
            $authUser = Auth::user();
            $created_test = $this->testService->addNewTest($request->validated(), $authUser);

            return response()->json(['created_test' => $created_test], 201);
            
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                 return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم', 'error' => $e->getMessage()], 500);
            }
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function show_tests(Request $request)
    {
         $request->validate([
            'daora_id' => 'required|integer|exists:daoras,id',
        ]);

        $authUser = Auth::user();
        $data = $this->testService->getTests($request->daora_id, $authUser);

        return response()->json([
            'tests' => $data['tests'],
            'registered_tests' => $data['registered_tests'],
        ]);
    }

    public function accept_test($test_id)
    {
        try {
            $authUser = Auth::user();
            $user_test = $this->testService->acceptTest($test_id, $authUser);

            return response()->json($user_test);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 404 || $code == 403) {
                 return response()->json(['error' => $e->getMessage()]);
            }
            return response()->json($e->getMessage()); // Matching the old response format directly
        }
    }

    public function accept_test_for_student(Request $request, $test_id)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer|exists:users,id'
            ]);

            $authUser = Auth::user();
            $user_test = $this->testService->acceptTestForStudent($test_id, $request->student_id, $authUser);

            return response()->json($user_test);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $code == 500 ? 400 : $code); 
        }
    }

    public function delete_accepted_test_for_student(Request $request, $test_id)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer|exists:users,id'
            ]);

            $authUser = Auth::user();
            $deleted = $this->testService->deleteAcceptedTestForStudent($test_id, $request->student_id, $authUser);

            return response()->json(['deleted' => $deleted]);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $code == 500 ? 400 : $code); 
        }
    }

    public function delete_accepted_test($test_id)
    {
        try {
            $authUser = Auth::user();
            $deleted = $this->testService->deleteAcceptedTest($test_id, $authUser);

            return response()->json(['deleted' => $deleted]);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 404 || $code == 403) {
                 return response()->json(['error' => $e->getMessage()]);
            }
            return response()->json($e->getMessage()); 
        }
    }

    public function delete_test($test_id)
    {
        try {
            $authUser = Auth::user();
            $this->testService->deleteTest($test_id, $authUser);

            return response()->json([
                'message' => 'تم حذف السبر وكل ما يتعلق به بنجاح'
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 403) {
                return response()->json(['error' => $e->getMessage()], 403);
            }
            if ($code == 404) {
                 return response()->json(['error' => $e->getMessage()], 404);
            }
            return response()->json([
                'error' => 'حصل خطأ أثناء الحذف',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function show_test_accepters($test_id)
    {
        try {
             $tests = $this->testService->showTestAccepters($test_id);
             return response()->json($tests);
        } catch (\Exception $e) {
            return response()->json([
                $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
    }


    public function update_test_accepter_data(Request $request, $test_id, $user_id)
    {
        try {
            // Can be extracted to FormRequest, but adhering to the old structure
             $request->validate([
                'the_part_to_test_in' => 'required',
                'rating' => 'required',
                'notes' => 'required',
            ]);

            $authUser = Auth::user();
            $updated = $this->testService->updateTestAccepterData($test_id, $user_id, $request->all(), $authUser);

            return response()->json(['updated' => $updated]);

        } catch (\Illuminate\Validation\ValidationException $e) {
             return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة ', 'errors' => $e->errors()], 403);
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 403) {
                 return response()->json(['message' => $e->getMessage()]);
            }
            if ($code == 404) {
                 return response()->json(['error' => $e->getMessage()]);
            }
             return response()->json($e->getMessage());
        }
    }


    public function show_success_students_in_test($test_id)
    {
         try {
            $data = $this->testService->showSuccessStudentsInTest($test_id);
            return response()->json($data);
         } catch (\Exception $e) {
             return response()->json([
                'message' => $e->getMessage(), 
                $e->getTrace()
            ]);
         }
    }

    public function make_aukaf_test_for_success_students(AddNewTestRequest $request, $test_id)
    {
        try {
            $authUser = Auth::user();
            $data = $this->testService->makeAukafTestForSuccessStudents($test_id, $request->validated(), $authUser);

            return response()->json([
                'aukaf_test'  => $data['aukaf_test'],
                'aukaf_users' => $data['aukaf_users']
            ]);

        } catch (\Exception $e) {
             $code = $e->getCode() > 0 ? $e->getCode() : 500;
             if ($code == 500) {
                 return response()->json(['error' => $e->getMessage()], 500);
             }
             return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function update_aukaf_tests_after_the_test(UpdateAukafTestRequest $request, $test_id, $user_id)
    {
        try {
            $this->testService->updateAukafTestsAfterTest($test_id, $user_id, $request->validated());

            return response()->json([
                'status' => 'success',
                'messages' => 'تم تعديل بيانات الاختبار بنجاح'
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 404) {
                return response()->json(['messages' => $e->getMessage()], 404);
            }
            return response()->json([
                'status' => 'error',
                'messages' => 'حدث خطأ غير متوقع',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
