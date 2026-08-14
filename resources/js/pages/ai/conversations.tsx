import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Conversations', href: '/ai/conversations' }];

type Message = {
    id: number;
    role: string;
    body: string;
};

type Conversation = {
    id: number;
    contact: string | null;
    channel: string;
    status: string;
    summary: string | null;
    messages_count: number;
    messages: Message[];
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function roleVariant(role: string): 'default' | 'secondary' | 'outline' {
    if (role === 'assistant') return 'default';
    if (role === 'system') return 'outline';

    return 'secondary';
}

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'open' ? 'default' : 'secondary';
}

function ReplyForm({ conversation }: { conversation: Conversation }) {
    const form = useForm<{ message: string }>({ message: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ai.conversations.reply', conversation.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-2 border-t pt-3">
            <Label htmlFor={`reply_${conversation.id}`}>Visitor message</Label>
            <textarea
                id={`reply_${conversation.id}`}
                className={textareaClass}
                value={form.data.message}
                placeholder="Type what the visitor said; the agent drafts the reply."
                onChange={(e) => form.setData('message', e.target.value)}
            />
            <InputError message={form.errors.message} />
            <div className="flex flex-wrap gap-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Generate reply
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => router.post(route('ai.conversations.summarize', conversation.id), {}, { preserveScroll: true })}
                >
                    Summarize
                </Button>
            </div>
        </form>
    );
}

export default function AiConversations({ conversations }: { conversations: Conversation[] }) {
    const { can } = usePermissions();
    const canUse = can('ai.agent.use');

    const aiError = usePage<SharedData>().props.errors.ai;

    const start = () => router.post(route('ai.conversations.store'), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Conversations" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="AI conversations" description="Chatbot threads handled by the agent, with the full transcript kept on record" />
                    {canUse && (
                        <Button variant="secondary" onClick={start}>
                            Start conversation
                        </Button>
                    )}
                </div>

                {aiError && (
                    <Alert variant="destructive">
                        <TriangleAlert className="h-4 w-4" />
                        <AlertTitle>The agent could not run</AlertTitle>
                        <AlertDescription>{aiError}</AlertDescription>
                    </Alert>
                )}

                {conversations.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No conversations yet. Threads appear here once the chatbot is engaged.</p>
                ) : (
                    <div className="space-y-3">
                        {conversations.map((conversation) => (
                            <Card key={conversation.id}>
                                <CardContent className="space-y-3 p-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{conversation.contact ?? 'Anonymous visitor'}</span>
                                        <Badge variant="outline">{conversation.channel}</Badge>
                                        <Badge variant={statusVariant(conversation.status)}>{conversation.status}</Badge>
                                        <span className="text-muted-foreground text-sm">{conversation.messages_count} messages</span>
                                    </div>

                                    {conversation.summary !== null && (
                                        <div>
                                            <p className="text-muted-foreground text-sm">Summary</p>
                                            <p className="text-sm whitespace-pre-wrap">{conversation.summary}</p>
                                        </div>
                                    )}

                                    {conversation.messages.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">No messages in this thread yet.</p>
                                    ) : (
                                        <div className="divide-y rounded-lg border">
                                            {conversation.messages.map((message) => (
                                                <div key={message.id} className="flex gap-3 p-3">
                                                    <Badge variant={roleVariant(message.role)} className="h-fit shrink-0">
                                                        {message.role}
                                                    </Badge>
                                                    <p className="text-sm whitespace-pre-wrap">{message.body}</p>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {canUse && <ReplyForm conversation={conversation} />}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
