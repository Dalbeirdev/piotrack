<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Tenant-scoped global search (SRCH). Searches the entities that exist today —
 * organizations, members, teams, invoices, files — grouped by type and filtered
 * by the viewer's permissions. Broadens as CRM/marketing modules add searchable
 * records.
 */
class GlobalSearch
{
    /**
     * @return array<int, array{type: string, label: string, items: list<array{title: string, subtitle: ?string, url: string}>}>
     */
    public function search(User $user, Organization $organization, string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $like = '%'.str_replace('%', '\%', $term).'%';
        $groups = [];

        // Organizations the user belongs to.
        $orgs = $user->activeOrganizations()->whereLike('name', $like)->limit(5)->get();
        if ($orgs->isNotEmpty()) {
            $groups[] = $this->group('Organizations', $orgs->map(fn (Organization $o) => [
                'title' => $o->name,
                'subtitle' => null,
                'url' => route('billing.index'),
            ])->all());
        }

        if (Gate::forUser($user)->allows('crm.contact.read')) {
            $contacts = Contact::query()
                ->where(fn ($q) => $q->whereLike('first_name', $like)->orWhereLike('last_name', $like)->orWhereLike('email', $like))
                ->limit(5)->get();
            if ($contacts->isNotEmpty()) {
                $groups[] = $this->group('Contacts', $contacts->map(fn (Contact $c) => [
                    'title' => $c->fullName(),
                    'subtitle' => $c->email,
                    'url' => route('crm.contacts.show', $c->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('crm.company.read')) {
            $companies = Company::query()
                ->where(fn ($q) => $q->whereLike('name', $like)->orWhereLike('domain', $like))
                ->limit(5)->get();
            if ($companies->isNotEmpty()) {
                $groups[] = $this->group('Companies', $companies->map(fn (Company $c) => [
                    'title' => $c->name,
                    'subtitle' => $c->domain,
                    'url' => route('crm.companies.show', $c->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('crm.deal.read')) {
            $deals = Deal::whereLike('name', $like)->limit(5)->get();
            if ($deals->isNotEmpty()) {
                $groups[] = $this->group('Deals', $deals->map(fn (Deal $d) => [
                    'title' => $d->name,
                    'subtitle' => ucfirst($d->status),
                    'url' => route('crm.deals.show', $d->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('members.view')) {
            $members = $organization->members()
                ->where(fn ($q) => $q->whereLike('name', $like)->orWhereLike('email', $like))
                ->limit(5)->get();
            if ($members->isNotEmpty()) {
                $groups[] = $this->group('Members', $members->map(fn (User $m) => [
                    'title' => $m->name,
                    'subtitle' => $m->email,
                    'url' => route('members.index'),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('teams.view')) {
            $teams = Team::whereLike('name', $like)->limit(5)->get();
            if ($teams->isNotEmpty()) {
                $groups[] = $this->group('Teams', $teams->map(fn (Team $t) => [
                    'title' => $t->name,
                    'subtitle' => null,
                    'url' => route('teams.index'),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('billing.view')) {
            $invoices = Invoice::where('organization_id', $organization->id)
                ->whereLike('number', $like)->limit(5)->get();
            if ($invoices->isNotEmpty()) {
                $groups[] = $this->group('Invoices', $invoices->map(fn (Invoice $i) => [
                    'title' => $i->number,
                    'subtitle' => ucfirst($i->status),
                    'url' => route('billing.invoices.show', $i->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('files.view')) {
            $files = File::whereLike('name', $like)->limit(5)->get();
            if ($files->isNotEmpty()) {
                $groups[] = $this->group('Files', $files->map(fn (File $f) => [
                    'title' => $f->name,
                    'subtitle' => null,
                    'url' => route('files.index'),
                ])->all());
            }
        }

        return $groups;
    }

    /**
     * @param  list<array{title: string, subtitle: ?string, url: string}>  $items
     * @return array{type: string, label: string, items: list<array{title: string, subtitle: ?string, url: string}>}
     */
    private function group(string $label, array $items): array
    {
        return ['type' => strtolower($label), 'label' => $label, 'items' => $items];
    }
}
