<?php

use App\Http\Controllers\Ai\AiActionController;
use App\Http\Controllers\Ai\AiAgentController;
use App\Http\Controllers\Ai\AiDashboardController;
use App\Http\Controllers\Ai\AiPromptTemplateController;
use App\Http\Controllers\Ai\AiVisibilityController;
use Illuminate\Support\Facades\Route;

/*
 * AI (Stage 12). Requires an authenticated, verified user with an active
 * organization on a plan that includes the `ai` feature; actions are gated by
 * ai.* permissions. Route-model binding is tenant-scoped.
 *
 * Note the deliberate split: `ai.agent.use` may PROPOSE sensitive actions, but
 * only `ai.actions.approve` can confirm one into effect (AIPF-006).
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:ai'])
    ->prefix('ai')->name('ai.')->group(function () {

        Route::get('/', AiDashboardController::class)->middleware('can:ai.view')->name('dashboard');

        // Sales agent.
        Route::get('agent', [AiAgentController::class, 'index'])->middleware('can:ai.view')->name('agent.index');
        Route::get('conversations', [AiAgentController::class, 'conversations'])->middleware('can:ai.view')->name('conversations.index');
        Route::middleware('can:ai.agent.use')->group(function () {
            Route::post('agent/run', [AiAgentController::class, 'run'])->name('agent.run');
            Route::post('agent/objection', [AiAgentController::class, 'objection'])->name('agent.objection');
            Route::post('agent/crm-update', [AiAgentController::class, 'proposeCrmUpdate'])->name('agent.crm-update');
            Route::post('conversations', [AiAgentController::class, 'startConversation'])->name('conversations.store');
            Route::post('conversations/{conversation}/reply', [AiAgentController::class, 'reply'])->name('conversations.reply');
            Route::post('conversations/{conversation}/summarize', [AiAgentController::class, 'summarize'])->name('conversations.summarize');
        });

        // Approval queue for sensitive actions.
        Route::get('actions', [AiActionController::class, 'index'])->middleware('can:ai.view')->name('actions.index');
        Route::middleware('can:ai.actions.approve')->group(function () {
            Route::post('actions/{action}/approve', [AiActionController::class, 'approve'])->name('actions.approve');
            Route::post('actions/{action}/reject', [AiActionController::class, 'reject'])->name('actions.reject');
        });

        // Prompt templates (versioned).
        Route::get('prompts', [AiPromptTemplateController::class, 'index'])->middleware('can:ai.view')->name('prompts.index');
        Route::middleware('can:ai.prompts.manage')->group(function () {
            Route::post('prompts/publish', [AiPromptTemplateController::class, 'publish'])->name('prompts.publish');
            Route::post('prompts/activate', [AiPromptTemplateController::class, 'activate'])->name('prompts.activate');
        });
    });

/*
 * AI visibility (AIVIS) rides the existing `ai_visibility` entitlement from
 * Stage 7 rather than the `ai` feature, keeping AEO/GEO customers on one gate.
 */
Route::middleware(['auth', 'verified', 'organization', 'entitlement:ai_visibility'])
    ->prefix('ai')->name('ai.')->group(function () {
        Route::get('visibility', [AiVisibilityController::class, 'index'])->middleware('can:ai.view')->name('visibility.index');
        Route::middleware('can:ai.prompts.manage')->group(function () {
            Route::post('visibility/prompts', [AiVisibilityController::class, 'storePrompt'])->name('visibility.prompts.store');
            Route::delete('visibility/prompts/{prompt}', [AiVisibilityController::class, 'destroyPrompt'])->name('visibility.prompts.destroy');
            Route::post('visibility/run', [AiVisibilityController::class, 'run'])->name('visibility.run');
        });
    });
