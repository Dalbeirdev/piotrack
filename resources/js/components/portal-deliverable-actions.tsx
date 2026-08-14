import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

export type PortalDeliverable = {
    id: number;
    project_id: number;
    title: string;
    type: string;
    status: string;
    approval_status: string;
    due_on: string | null;
    approved_at: string | null;
    rejection_reason: string | null;
};

function RequestChangesDialog({ deliverable }: { deliverable: PortalDeliverable }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ reason: string }>({ reason: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('portal.deliverables.reject', deliverable.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Request changes
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Request changes to {deliverable.title}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">Your feedback goes back to the delivery team and the work returns to in progress.</p>
                    <div className="grid gap-1">
                        <Label htmlFor={`portal_reject_${deliverable.id}`}>What needs to change? (required)</Label>
                        <textarea
                            id={`portal_reject_${deliverable.id}`}
                            className={textareaClass}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing || form.data.reason.trim() === ''}>
                            Send feedback
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The only thing a client can decide in the portal. Approving and requesting
 * changes carry the same visual weight — neither is presented as the expected
 * answer.
 */
export function PortalDeliverableActions({ deliverable }: { deliverable: PortalDeliverable }) {
    if (deliverable.approval_status !== 'pending') {
        return null;
    }

    const approve = () => router.post(route('portal.deliverables.approve', deliverable.id), {}, { preserveScroll: true });

    return (
        <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" onClick={approve}>
                Approve
            </Button>
            <RequestChangesDialog deliverable={deliverable} />
        </div>
    );
}
