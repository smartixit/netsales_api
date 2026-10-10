<?php

namespace App\Http\Controllers;

use App\Models\ExpenseItem;
use App\Http\Requests\StoreExpenseItemRequest;
use App\Http\Requests\UpdateExpenseItemRequest;

class ExpenseItemController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseItemRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseItem $expenseItem)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ExpenseItem $expenseItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseItemRequest $request, ExpenseItem $expenseItem)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseItem $expenseItem)
    {
        //
    }
}
