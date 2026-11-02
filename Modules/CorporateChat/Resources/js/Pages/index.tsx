import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { ArrowLeft, Building2, MessageCircle, MoreVertical, Paperclip, Phone, Search, Send, Smile, Video } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ChatAvatar } from '../Components/chat-avatar';
import { ChatCategoryTabs, type ChatCategory } from '../Components/chat-category-tabs';
import { ChatMessage, type ChatMessageData } from '../Components/chat-message';

interface Conversation {
    id: number;
    name: string;
    category: Exclude<ChatCategory, 'All'>;
    subtitle: string;
    initials: string;
    color: string;
    online?: boolean;
    unread?: number;
    lastMessage: string;
    time: string;
    messages: ChatMessageData[];
}

const seed: Conversation[] = [
    {
        id: 1,
        name: 'Apollo Pipes Company',
        category: 'Company',
        subtitle: 'Company-wide announcements',
        initials: 'AP',
        color: 'bg-brand-teal',
        unread: 3,
        lastMessage: 'Quarterly town hall begins at 4 PM.',
        time: '10:42',
        messages: [
            { id: 1, sender: 'Corporate Communications', text: 'Good morning team. The quarterly town hall begins at 4 PM today.', time: '10:15' },
            { id: 2, text: 'Thank you. I will join the session.', time: '10:18', mine: true },
        ],
    },
    {
        id: 2,
        name: 'Sikandrabad Plant',
        category: 'Plant',
        subtitle: 'Production & dispatch team',
        initials: 'SP',
        color: 'bg-brand-gold',
        online: true,
        unread: 1,
        lastMessage: 'Line 2 production is on schedule.',
        time: '09:35',
        messages: [
            { id: 1, sender: 'Plant Operations', text: 'Line 2 production is on schedule. Dispatch will start after lunch.', time: '09:35' },
            { id: 2, text: 'Please share the final dispatch quantity.', time: '09:39', mine: true },
        ],
    },
    {
        id: 3,
        name: 'Head Office - Noida',
        category: 'Head Office',
        subtitle: 'Corporate operations',
        initials: 'HO',
        color: 'bg-brand-berry',
        online: true,
        lastMessage: 'The approval has been completed.',
        time: 'Yesterday',
        messages: [{ id: 1, sender: 'Administration', text: 'The vendor approval has been completed and updated.', time: '16:20' }],
    },
    {
        id: 4,
        name: 'Dadri Plant',
        category: 'Plant',
        subtitle: 'Maintenance coordination',
        initials: 'DP',
        color: 'bg-brand-plum text-white',
        lastMessage: 'Maintenance planned Sunday.',
        time: 'Yesterday',
        messages: [{ id: 1, sender: 'Maintenance Team', text: 'Preventive maintenance is planned Sunday from 8 AM.', time: '14:05' }],
    },
    {
        id: 5,
        name: 'Finance & Accounts',
        category: 'Head Office',
        subtitle: 'Finance help desk',
        initials: 'FA',
        color: 'bg-brand-gold',
        lastMessage: 'Expense cycle closes Friday.',
        time: 'Mon',
        messages: [{ id: 1, sender: 'Finance Team', text: 'Please submit pending expense claims before Friday.', time: '11:30' }],
    },
];

export default function ChatIndex() {
    const [category, setCategory] = useState<ChatCategory>('All');
    const [query, setQuery] = useState('');
    const [selectedId, setSelectedId] = useState(1);
    const [showConversation, setShowConversation] = useState(false);
    const [draft, setDraft] = useState('');
    const [items, setItems] = useState(seed);
    const filtered = useMemo(
        () => items.filter((item) => (category === 'All' || item.category === category) && item.name.toLowerCase().includes(query.toLowerCase())),
        [category, items, query],
    );
    const selected = items.find((item) => item.id === selectedId) ?? items[0];
    const send = () => {
        const text = draft.trim();
        if (!text) return;
        setItems((all) =>
            all.map((item) =>
                item.id === selected.id
                    ? { ...item, lastMessage: text, time: 'Now', messages: [...item.messages, { id: Date.now(), text, time: 'Now', mine: true }] }
                    : item,
            ),
        );
        setDraft('');
    };
    return (
        <AppLayout breadcrumbs={[{ title: 'Corporate chat', href: '/chat' }]}>
            <Head title="Corporate Chat" />
            <div className="bg-brand-aqua/70 flex min-h-0 flex-1 p-2 sm:p-4 lg:p-6">
                <section className="mx-auto grid min-h-[calc(100dvh-8rem)] w-full max-w-[1500px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:rounded-2xl lg:min-h-[660px] lg:grid-cols-[360px_1fr]">
                    <aside className={`${showConversation ? 'hidden' : 'flex'} min-h-0 flex-col border-r border-slate-200 lg:flex`}>
                        <div className="flex items-center justify-between px-4 py-4">
                            <div>
                                <p className="text-[10px] font-bold tracking-[.16em] text-slate-500 uppercase">APL Connect</p>
                                <h1 className="mt-1 text-xl font-bold text-slate-800">Corporate chat</h1>
                            </div>
                            <button className="grid size-10 place-items-center rounded-full bg-slate-700 text-white">
                                <MessageCircle className="size-5" />
                            </button>
                        </div>
                        <div className="px-4 pb-3">
                            <label className="flex h-10 items-center gap-2 rounded-lg border bg-slate-50 px-3">
                                <Search className="size-4 text-slate-400" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search conversations"
                                    className="w-full bg-transparent text-sm outline-none"
                                />
                            </label>
                        </div>
                        <ChatCategoryTabs active={category} onChange={setCategory} />
                        <div className="min-h-0 flex-1 overflow-y-auto border-t">
                            {filtered.map((item) => (
                                <button
                                    key={item.id}
                                    onClick={() => {
                                        setSelectedId(item.id);
                                        setShowConversation(true);
                                    }}
                                    className={`hover:bg-brand-aqua/60 flex w-full gap-3 border-b px-4 py-3.5 text-left ${selected.id === item.id ? 'bg-brand-aqua/60 border-l-4 border-l-slate-600' : 'border-l-4 border-l-transparent'}`}
                                >
                                    <ChatAvatar label={item.initials} color={item.color} online={item.online} />
                                    <span className="min-w-0 flex-1">
                                        <span className="flex justify-between gap-2">
                                            <b className="truncate text-sm text-slate-800">{item.name}</b>
                                            <small className="text-slate-400">{item.time}</small>
                                        </span>
                                        <span className="block text-[10px] font-semibold text-slate-500">{item.category}</span>
                                        <span className="mt-1 flex justify-between gap-2">
                                            <span className="truncate text-xs text-slate-500">{item.lastMessage}</span>
                                            {item.unread && (
                                                <span className="grid size-5 place-items-center rounded-full bg-slate-700 text-[9px] text-white">
                                                    {item.unread}
                                                </span>
                                            )}
                                        </span>
                                    </span>
                                </button>
                            ))}
                        </div>
                    </aside>
                    <div className={`${showConversation ? 'flex' : 'hidden'} bg-brand-aqua/30 min-h-0 flex-col lg:flex`}>
                        <header className="flex h-[68px] items-center gap-2 border-b bg-white px-3 sm:h-[76px] sm:gap-3 sm:px-5">
                            <button
                                type="button"
                                onClick={() => setShowConversation(false)}
                                className="hover:bg-brand-aqua grid size-9 shrink-0 place-items-center rounded-full text-slate-600 lg:hidden"
                                aria-label="Back to conversations"
                            >
                                <ArrowLeft className="size-[18px]" />
                            </button>
                            <ChatAvatar label={selected.initials} color={selected.color} online={selected.online} />
                            <div className="min-w-0 flex-1">
                                <h2 className="truncate text-sm font-bold">{selected.name}</h2>
                                <p className="flex items-center gap-1 text-xs text-slate-500">
                                    <Building2 className="size-3" />
                                    {selected.subtitle}
                                </p>
                            </div>
                            {[Phone, Video, Search, MoreVertical].map((Icon, i) => (
                                <button
                                    key={i}
                                    className={`${i < 2 ? 'hidden sm:grid' : 'grid'} hover:bg-brand-aqua size-9 shrink-0 place-items-center rounded-full text-slate-500`}
                                >
                                    <Icon className="size-[18px]" />
                                </button>
                            ))}
                        </header>
                        <div className="flex-1 space-y-3 overflow-y-auto p-3 sm:p-5 lg:p-8">
                            <div className="bg-brand-gold mx-auto w-fit rounded-full px-3 py-1 text-[10px] font-bold text-slate-600">Today</div>
                            {selected.messages.map((message) => (
                                <ChatMessage key={message.id} message={message} />
                            ))}
                        </div>
                        <div className="border-t bg-white p-3">
                            <div className="flex items-end gap-2 rounded-xl border bg-slate-50 p-2">
                                <button className="hidden size-9 place-items-center text-slate-400 sm:grid">
                                    <Smile className="size-5" />
                                </button>
                                <button className="grid size-9 place-items-center text-slate-400">
                                    <Paperclip className="size-5" />
                                </button>
                                <textarea
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            send();
                                        }
                                    }}
                                    rows={1}
                                    placeholder="Type a message"
                                    className="min-h-9 flex-1 resize-none bg-transparent py-2 text-sm outline-none"
                                />
                                <button onClick={send} className="grid size-10 place-items-center rounded-lg bg-slate-700 text-white">
                                    <Send className="size-[18px]" />
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
