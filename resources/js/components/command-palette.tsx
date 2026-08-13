import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type ResultItem = { title: string; subtitle: string | null; url: string };
type ResultGroup = { type: string; label: string; items: ResultItem[] };

const RECENT_KEY = 'piotrack:recent-searches';

function loadRecent(): string[] {
    try {
        return JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');
    } catch {
        return [];
    }
}

/**
 * Global search command palette (SRCH). Open with ⌘K / Ctrl-K or the header
 * button. Fetches grouped, tenant-scoped, permission-filtered results and
 * remembers recent queries client-side.
 */
export function CommandPalette() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<ResultGroup[]>([]);
    const [recent, setRecent] = useState<string[]>([]);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (open) {
            setRecent(loadRecent());
        } else {
            setQuery('');
            setGroups([]);
        }
    }, [open]);

    useEffect(() => {
        if (debounce.current) clearTimeout(debounce.current);
        if (query.trim() === '') {
            setGroups([]);
            return;
        }
        debounce.current = setTimeout(() => {
            fetch(`${route('search')}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((data) => setGroups(data.groups ?? []))
                .catch(() => setGroups([]));
        }, 200);
    }, [query]);

    const go = (url: string) => {
        const trimmed = query.trim();
        if (trimmed) {
            const next = [trimmed, ...loadRecent().filter((q) => q !== trimmed)].slice(0, 5);
            localStorage.setItem(RECENT_KEY, JSON.stringify(next));
        }
        setOpen(false);
        router.visit(url);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-muted-foreground hover:bg-muted flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
            >
                <Search className="size-4" />
                <span className="hidden sm:inline">Search…</span>
                <kbd className="bg-muted ml-2 hidden rounded px-1 text-xs sm:inline">⌘K</kbd>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="top-24 max-w-lg translate-y-0 p-0">
                    <DialogTitle className="sr-only">Search</DialogTitle>
                    <div className="border-b p-3">
                        <Input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search organizations, members, teams, invoices, files…"
                            className="border-0 focus-visible:ring-0"
                        />
                    </div>
                    <div className="max-h-80 overflow-y-auto p-2">
                        {query.trim() === '' ? (
                            recent.length > 0 ? (
                                <div>
                                    <p className="text-muted-foreground px-2 py-1 text-xs">Recent</p>
                                    {recent.map((r) => (
                                        <button
                                            key={r}
                                            onClick={() => setQuery(r)}
                                            className="hover:bg-muted block w-full rounded px-2 py-1.5 text-left text-sm"
                                        >
                                            {r}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground px-2 py-6 text-center text-sm">Start typing to search.</p>
                            )
                        ) : groups.length === 0 ? (
                            <p className="text-muted-foreground px-2 py-6 text-center text-sm">No results.</p>
                        ) : (
                            groups.map((group) => (
                                <div key={group.type} className="mb-2">
                                    <p className="text-muted-foreground px-2 py-1 text-xs">{group.label}</p>
                                    {group.items.map((item, i) => (
                                        <button
                                            key={i}
                                            onClick={() => go(item.url)}
                                            className="hover:bg-muted flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm"
                                        >
                                            <span>{item.title}</span>
                                            {item.subtitle && <span className="text-muted-foreground text-xs">{item.subtitle}</span>}
                                        </button>
                                    ))}
                                </div>
                            ))
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
