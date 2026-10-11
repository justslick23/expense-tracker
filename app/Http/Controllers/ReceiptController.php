<?php



namespace App\Http\Controllers;



use App\Models\Expense;

use App\Models\FinancialPeriod;

use App\Models\Receipt;

use App\Services\GeminiReceiptScanner;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Str;

use Illuminate\Validation\Rules\File;

use Illuminate\Validation\ValidationException;

use Throwable;





class ReceiptController extends Controller

{

    public function create()

    {

        return view('receipts.create');

    }





public function index(Request $request)

{

    $receipts = $request->user()

        ->receipts()

        ->withCount('expenses')

        ->latest()

        ->paginate(12);



    return view('receipts.index', compact('receipts'));

}



    public function store(Request $request)

    {

        $validated = $request->validate([

            'receipt' => [

                'required',

                File::types(['jpg', 'jpeg', 'png', 'webp'])

                    ->max('10mb'),

            ],

        ]);



        $file = $validated['receipt'];



        $path = $file->store(

            'receipts/' . $request->user()->id,

            'local'

        );



        if (! $path) {

            return back()->withErrors([

                'receipt' => 'Unable to save the receipt.',

            ]);

        }



        try {

            $receipt = $request->user()->receipts()->create([

                'original_filename' => Str::limit(

                    $file->getClientOriginalName(),

                    255,

                    ''

                ),

                'file_path' => $path,

                'mime_type' => $file->getMimeType(),

                'file_size' => $file->getSize(),

                'status' => 'uploaded',

                'currency' => 'LSL',

            ]);

        } catch (Throwable $exception) {

            Storage::disk('local')->delete($path);

            throw $exception;

        }



        return redirect()

            ->route('receipts.show', $receipt)

            ->with(

                'success',

                'Receipt uploaded successfully. Ready for scanning.'

            );

    }



    public function show(Request $request, Receipt $receipt)

    {

        $this->checkOwnership($request, $receipt);



        return view('receipts.show', compact('receipt'));

    }





public function image(Request $request, Receipt $receipt)

{

    $this->checkOwnership($request, $receipt);



    $disk = Storage::disk('local');



    abort_unless(

        $disk->exists($receipt->file_path),

        404,

        'Receipt image not found.'

    );



    return response()->file(

        $disk->path($receipt->file_path),

        [

            'Content-Type' => $receipt->mime_type,

            'Content-Disposition' => 'inline',

            'Cache-Control' => 'private, no-store',

            'X-Content-Type-Options' => 'nosniff',

        ]

    );

}





    public function scan(

        Request $request,

        Receipt $receipt,

        GeminiReceiptScanner $scanner

    ) {

        $this->checkOwnership($request, $receipt);



        if (

            $receipt->expense_id !== null ||

            $receipt->status === 'confirmed'

        ) {

            return back()->withErrors([

                'scan' => 'This receipt is already confirmed.',

            ]);

        }



        if ($receipt->status === 'processing') {

            return back()->withErrors([

                'scan' => 'This receipt is already being processed.',

            ]);

        }



        $receipt->update([

            'status' => 'processing',

            'processing_error' => null,

        ]);



        try {



            $categories = $request->user()

            ->categories()

            ->where('type', 'expense')

            ->orderBy('name')

            ->get(['id', 'name']);



        $categoryIds = $categories

            ->pluck('id')

            ->map(fn ($id) => (int) $id)

            ->all();



        $data = $scanner->scan(

            $receipt,

            $categories->toArray()

        );



            $validated = Validator::make($data, [

                'merchant' => [

                    'nullable',

                    'string',

                    'max:255',

                ],

                'transaction_date' => [

                    'nullable',

                    'date_format:Y-m-d',

                ],

                'total_amount' => [

                    'nullable',

                    'string',

                    'regex:/^\d{1,12}\\.\d{2}$/',

                ],

                'currency' => [

                    'nullable',

                    'string',

                    'size:3',

                    'alpha',

                ],

                'items' => [

                    'required',

                    'array',

                    'max:100',

                ],

                'items.*.raw_name' => [

                    'required',

                    'string',

                    'max:255',

                ],

                'items.*.name' => [

                    'required',

                    'string',

                    'max:255',

                ],

                'items.*.quantity' => [

                    'nullable',

                    'string',

                    'max:50',

                ],

                'items.*.total' => [

                    'nullable',

                    'string',

                    'regex:/^-?\d{1,12}\\.\d{2}$/',

                ],

                'items.*.entry_type' => [

                    'required',

                    'in:product,discount,refund,adjustment,other',

                ],

               'items.*.suggested_category_id' => [

    'nullable',

    'integer',

    \Illuminate\Validation\Rule::in($categoryIds),

],

                'items.*.confidence' => [

                    'required',

                    'in:high,medium,low',

                ],

                'items.*.related_item_index' => [

                    'nullable',

                    'integer',

                    'min:0',

                    'max:99',

                ],

            ])->validate();



            // Remove invalid product associations instead of

            // automatically assigning discounts to wrong products.

            $items = $validated['items'];



            foreach ($items as $index => &$item) {

                $relatedIndex = $item['related_item_index'] ?? null;



                if ($relatedIndex === null) {

                    continue;

                }



                $relatedItem = $items[$relatedIndex] ?? null;



                if (

                    $relatedIndex === $index ||

                    ! $relatedItem ||

                    ($relatedItem['entry_type'] ?? null) !== 'product'

                ) {

                    $item['related_item_index'] = null;

                }

            }



            unset($item);



            $amount = null;



            if ($validated['total_amount'] !== null) {

                [$whole, $cents] = explode(

                    '.',

                    $validated['total_amount']

                );



                $amount = ((int) $whole * 100) + (int) $cents;

            }



            $receipt->update([

                'status' => 'processed',

                'merchant' => $validated['merchant'] ?? null,

                'transaction_date' =>

                    $validated['transaction_date'] ?? null,

                'total_amount' => $amount,

                'currency' => strtoupper(

                    $validated['currency'] ?? 'LSL'

                ),

                'items' => $items,

                'extracted_data' => $data,

                'ai_model' => config('services.gemini.model'),

                'processed_at' => now(),

                'processing_error' => null,

            ]);



            return redirect()

                ->route('receipts.show', $receipt)

                ->with(

                    'success',

                    'Receipt scanned. Review the items and discounts.'

                );



        } catch (Throwable $exception) {

            report($exception);



            $receipt->update([

                'status' => 'failed',

                'processing_error' => 'Receipt scanning failed.',

            ]);



            return redirect()

                ->route('receipts.show', $receipt)

                ->withErrors([

                    'scan' => 'Gemini could not process the receipt. ' .

                        'Check the image or try scanning again.',

                ]);

        }

    }







    public function review(Request $request, Receipt $receipt)

    {

        $this->checkOwnership($request, $receipt);



        abort_unless($receipt->status === 'processed', 403);



        $categories = $request->user()

            ->categories()

            ->where('type', 'expense')

            ->orderBy('name')

            ->get(['id', 'name']);



        return view('receipts.review', compact(

            'receipt',

            'categories'

        ));

    }



    public function updateReview(Request $request, Receipt $receipt)

    {

        $this->checkOwnership($request, $receipt);



        abort_unless($receipt->status === 'processed', 403);



        $existingItems = $receipt->items ?? [];



        $categoryIds = $request->user()

            ->categories()

            ->where('type', 'expense')

            ->pluck('id')

            ->map(fn ($id) => (int) $id)

            ->all();



        $validated = $request->validate([

            'merchant' => ['required', 'string', 'max:255'],

            'transaction_date' => ['required', 'date_format:Y-m-d'],

            'total_amount' => [

                'required',

                'regex:/^\d{1,12}\\.\d{2}$/',

            ],

            'items' => ['required', 'array', 'size:' . count($existingItems)],

            'items.*.name' => ['required', 'string', 'max:255'],

            'items.*.total' => [

                'required',

                'regex:/^-?\d{1,12}\\.\d{2}$/',

            ],

            'items.*.category_id' => [

                'nullable',

                'integer',

                \Illuminate\Validation\Rule::in($categoryIds),

            ],

        ]);



        // Require exactly one submitted row for each original item.

        $submittedItems = $validated['items'];



        if (

            array_keys($submittedItems) !==

            range(0, count($existingItems) - 1)

        ) {

            return back()

                ->withErrors([

                    'items' => 'Receipt item rows are incomplete or invalid.',

                ])

                ->withInput();

        }



        $updatedItems = [];



        foreach ($existingItems as $index => $original) {

            $edited = $submittedItems[$index];



            $signedAmount = $edited['total'];

            $entryType = $original['entry_type'] ?? 'other';



            if (

                $entryType === 'product' &&

                str_starts_with($signedAmount, '-')

            ) {

                return back()

                    ->withErrors([

                        "items.$index.total" =>

                            'Purchased products cannot have negative amounts.',

                    ])

                    ->withInput();

            }



            if (

                $entryType === 'discount' &&

                !str_starts_with($signedAmount, '-')

            ) {

                return back()

                    ->withErrors([

                        "items.$index.total" =>

                            'A discount must have a negative amount.',

                    ])

                    ->withInput();

            }



            // Preserve the original printed receipt information.

            $updatedItems[] = array_merge($original, [

                'name' => trim($edited['name']),

                'total' => $signedAmount,

                'category_id' => isset($edited['category_id'])

                    ? (int) $edited['category_id']

                    : null,

            ]);

        }



        $toMinorUnits = static function (string $amount): int {

            [$whole, $cents] = explode('.', $amount);



            return ((int) $whole * 100) + (int) $cents;

        };



        $receipt->update([

            'merchant' => trim($validated['merchant']),

            'transaction_date' => $validated['transaction_date'],

            'total_amount' => $toMinorUnits(

                $validated['total_amount']

            ),

            'items' => $updatedItems,

        ]);



        return redirect()

            ->route('receipts.review', $receipt)

            ->with('success', 'Receipt review saved successfully.');

    }





    public function confirm(Request $request, Receipt $receipt)

    {

        $this->checkOwnership($request, $receipt);



        try {

            $createdExpenses = DB::transaction(function () use (

                $request,

                $receipt

            ) {

                // Lock the receipt to prevent two simultaneous confirmations.

                $receipt = Receipt::query()

                    ->where('id', $receipt->id)

                    ->where('user_id', $request->user()->id)

                    ->lockForUpdate()

                    ->firstOrFail();



                if (

                    $receipt->status !== 'processed' ||

                    $receipt->expense_id !== null ||

                    $receipt->expenses()->exists()

                ) {

                    throw ValidationException::withMessages([

                        'confirmation' =>

                            'This receipt cannot be confirmed again.',

                    ]);

                }



                if (

                    ! $receipt->merchant ||

                    ! $receipt->transaction_date ||

                    $receipt->total_amount === null ||

                    $receipt->total_amount <= 0

                ) {

                    throw ValidationException::withMessages([

                        'confirmation' =>

                            'Please complete the receipt details first.',

                    ]);

                }



                if ($receipt->currency !== 'LSL') {

                    throw ValidationException::withMessages([

                        'confirmation' =>

                            'Only LSL receipts are supported for now.',

                    ]);

                }



                $date = $receipt->transaction_date->toDateString();



                // Financial periods must belong to the current user.

                $period = FinancialPeriod::query()

                    ->where('user_id', $request->user()->id)

                    ->where('is_open', true)

                    ->whereDate('start_date', '<=', $date)

                    ->where(function ($query) use ($date) {

                        $query->whereNull('end_date')

                            ->orWhereDate('end_date', '>=', $date);

                    })

                    ->first();



                if (! $period) {

                    throw ValidationException::withMessages([

                        'confirmation' =>

                            'This transaction date is outside your open financial period.',

                    ]);

                }



                $items = $receipt->items ?? [];



                if (! is_array($items) || count($items) === 0) {

                    throw ValidationException::withMessages([

                        'confirmation' =>

                            'This receipt has no reviewed items.',

                    ]);

                }



                $validCategoryIds = $request->user()

                    ->categories()

                    ->where('type', 'expense')

                    ->pluck('id')

                    ->map(fn ($id) => (int) $id)

                    ->all();



                $products = [];
                $discounts = [];

                $lineTotal = 0;



                foreach ($items as $index => $item) {

                    $categoryId = $item['category_id'] ?? null;

                    $value = $item['total'] ?? null;

                    $type = $item['entry_type'] ?? 'other';



                    // Every monetary line needs a real expense category.

                    if (

                        $categoryId === null ||

                        ! in_array(

                            (int) $categoryId,

                            $validCategoryIds,

                            true

                        )

                    ) {

                        throw ValidationException::withMessages([

                            'confirmation' =>

                                'Assign a valid category to every receipt item and discount.',

                        ]);

                    }



                    if (

                        ! is_string($value) ||

                        ! preg_match(

                            '/^-?\d{1,12}\\.\d{2}$/',

                            $value

                        )

                    ) {

                        throw ValidationException::withMessages([

                            'confirmation' =>

                                'One or more receipt amounts are invalid.',

                        ]);

                    }



                    // Interpret monetary values using integer cents.

                    $negative = str_starts_with($value, '-');

                    $unsigned = $negative

                        ? substr($value, 1)

                        : $value;



                    [$whole, $cents] = explode('.', $unsigned);



                    $amount = ((int) $whole * 100) + (int) $cents;



                    if ($negative) {

                        $amount *= -1;

                    }



                    if (

                        ($type === 'product' && $amount < 0) ||

                        ($type === 'discount' && $amount >= 0)

                    ) {

                        throw ValidationException::withMessages([

                            'confirmation' =>

                                'Check the signs of product and discount amounts.',

                        ]);

                    }



                    // Refunds and adjustments may be positive or negative.

                    // They must still be assigned to the correct category.

                    if (! in_array($type, [

                        'product',

                        'discount',

                        'refund',

                        'adjustment',

                        'other',

                    ], true)) {

                        throw ValidationException::withMessages([

                            'confirmation' =>

                                'An unrecognised receipt entry was found.',

                        ]);

                    }



                    $lineTotal += $amount;

                    $categoryId = (int) $categoryId;

                    if ($type === 'product') {
                        $products[$index] = [
                            'category_id' => $categoryId,
                            'amount' => $amount,
                            'name' => trim((string) ($item['name'] ?? '')),
                        ];
                    } elseif ($type === 'discount') {
                        $discounts[] = [
                            'amount' => $amount,
                            'related_item_index' => $item['related_item_index'] ?? null,
                            'category_id' => $categoryId,
                        ];
                    } else {
                        throw ValidationException::withMessages([
                            'confirmation' => 'Receipt contains a refund, adjustment or other line. Review it before confirming individual product expenses.',
                        ]);
                    }

                }



                if ($lineTotal !== (int) $receipt->total_amount) {

                    throw ValidationException::withMessages([

                        'confirmation' =>

                            'The reviewed item amounts do not match the receipt total.',

                    ]);

                }



                // Discounts reduce their linked product rather than becoming expenses.
                foreach ($discounts as $discount) {
                    $relatedIndex = $discount['related_item_index'];

                    if ($relatedIndex === null || !array_key_exists((int) $relatedIndex, $products)) {
                        throw ValidationException::withMessages([
                            'confirmation' => 'A discount is not linked to a purchased item. Review the receipt before confirming.',
                        ]);
                    }

                    $relatedIndex = (int) $relatedIndex;

                    if ($products[$relatedIndex]['category_id'] !== $discount['category_id']) {
                        throw ValidationException::withMessages([
                            'confirmation' => 'A discount category does not match its linked product.',
                        ]);
                    }

                    $products[$relatedIndex]['amount'] += $discount['amount'];
                }

                if (count($products) === 0) {
                    throw ValidationException::withMessages([
                        'confirmation' => 'No purchased products were found on this receipt.',
                    ]);
                }

                $created = [];

                // Save one expense per product, never a separate discount expense.
                foreach ($products as $product) {
                    if ($product['amount'] <= 0 || $product['name'] === '') {
                        throw ValidationException::withMessages([
                            'confirmation' => 'Each purchased product needs a description and a positive amount after discounts.',
                        ]);
                    }

                    $expense = Expense::query()->create([
                        'user_id' => $request->user()->id,
                        'financial_period_id' => $period->id,
                        'category_id' => $product['category_id'],
                        'amount' => $product['amount'],
                        'expense_date' => $date,
                        'merchant' => $receipt->merchant,
                        'description' => $product['name'],
                    ]);

                    $receipt->expenses()->attach($expense->id);
                    $created[] = $expense->id;
                }

                // Mark confirmed only after all expenses are created.

                $receipt->update([

                    'status' => 'confirmed',

                ]);



                return $created;

            }, 3);



            return redirect()

                ->route('receipts.show', $receipt)

                ->with(

                    'success',

                    count($createdExpenses) .

                    ' receipt item expense(s) saved successfully.'

                );



        } catch (ValidationException $exception) {

            throw $exception;

        }

    }





    private function checkOwnership(

        Request $request,

        Receipt $receipt

    ): void {

        abort_unless(

            (int) $receipt->user_id ===

                (int) $request->user()->id,

            404

        );

    }

}
