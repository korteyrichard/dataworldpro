import { useState, useEffect } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/components/ui/use-toast';

interface Settings {
    minimum_withdrawal_amount: number;
    minimum_wallet_withdrawal_amount: number;
    referral_commission_amount: number;
    agent_upgrade_fee: number;
    track_order_video_url: string;
    verify_topup_video_url: string;
    number_restriction_enabled: boolean;
    previous_order_restriction_enabled: boolean;
}

interface VerifiedNumber {
    id: number;
    phone_number: string;
    created_at: string;
}

interface UnverifiedNumber {
    id: number;
    phone_number: string;
    restriction_type: string;
    source: string;
    created_at: string;
}

interface PaginatedVerifiedNumbers {
    data: VerifiedNumber[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    auth: { user: any };
    settings: Settings;
    verifiedNumbers: PaginatedVerifiedNumbers;
    verifiedSearch: string;
    unverifiedCount: number;
    unverifiedNumbers: UnverifiedNumber[];
}

export default function Settings({ auth, settings, verifiedNumbers, verifiedSearch, unverifiedCount, unverifiedNumbers }: Props) {
    const { toast } = useToast();
    const { flash } = usePage().props as any;

    const { data, setData, post, processing, errors } = useForm({
        minimum_withdrawal_amount: settings.minimum_withdrawal_amount,
        minimum_wallet_withdrawal_amount: settings.minimum_wallet_withdrawal_amount,
        referral_commission_amount: settings.referral_commission_amount,
        agent_upgrade_fee: settings.agent_upgrade_fee,
        track_order_video_url: settings.track_order_video_url || '',
        verify_topup_video_url: settings.verify_topup_video_url || '',
    });

    const importForm = useForm<{ file: File | null }>({ file: null });

    const [newNumber, setNewNumber] = useState('');
    const [addingNumber, setAddingNumber] = useState(false);
    const [search, setSearch] = useState(verifiedSearch || '');
    const [unverifiedSearch, setUnverifiedSearch] = useState('');

    useEffect(() => {
        if (flash?.success) toast({ title: 'Success', description: flash.success });
        if (flash?.error) toast({ title: 'Error', description: flash.error, variant: 'destructive' });
    }, [flash, toast]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.update'));
    };

    const toggleNumberRestriction = () => {
        router.post(route('admin.toggle.number.restriction'), { enabled: !settings.number_restriction_enabled });
    };

    const togglePreviousOrderRestriction = () => {
        router.post(route('admin.toggle.previous.order.restriction'), { enabled: !settings.previous_order_restriction_enabled });
    };

    const addNumber = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newNumber || newNumber.length !== 10) return;
        setAddingNumber(true);
        router.post(route('admin.verified-numbers.store'), { phone_number: newNumber }, {
            onSuccess: () => { setNewNumber(''); setAddingNumber(false); },
            onError: () => setAddingNumber(false),
        });
    };

    const removeNumber = (id: number) => {
        router.delete(route('admin.verified-numbers.destroy', id));
    };

    const importNumbers = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importForm.data.file) return;
        importForm.post(route('admin.verified-numbers.import'), {
            forceFormData: true,
            onSuccess: () => importForm.reset(),
            onError: () => toast({ title: 'Error', description: 'Import failed. Please try again.', variant: 'destructive' }),
        });
    };

    return (
        <AdminLayout user={auth.user} header="Store Management Settings">
            <Head title="Store Management Settings" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                    {/* General Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Store Management Settings</CardTitle>
                            <CardDescription>
                                Configure settings for the store management system including withdrawal limits, referral amounts, upgrade fees, and tutorial video URLs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="minimum_withdrawal_amount">Minimum MoMo Withdrawal Amount (GHS)</Label>
                                        <Input
                                            id="minimum_withdrawal_amount"
                                            type="number" min="1" step="0.01"
                                            value={data.minimum_withdrawal_amount}
                                            onChange={(e) => setData('minimum_withdrawal_amount', parseFloat(e.target.value))}
                                            className={errors.minimum_withdrawal_amount ? 'border-red-500' : ''}
                                        />
                                        {errors.minimum_withdrawal_amount && <p className="text-sm text-red-500">{errors.minimum_withdrawal_amount}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="minimum_wallet_withdrawal_amount">Minimum Wallet Withdrawal Amount (GHS)</Label>
                                        <Input
                                            id="minimum_wallet_withdrawal_amount"
                                            type="number" min="1" step="0.01"
                                            value={data.minimum_wallet_withdrawal_amount}
                                            onChange={(e) => setData('minimum_wallet_withdrawal_amount', parseFloat(e.target.value))}
                                            className={errors.minimum_wallet_withdrawal_amount ? 'border-red-500' : ''}
                                        />
                                        {errors.minimum_wallet_withdrawal_amount && <p className="text-sm text-red-500">{errors.minimum_wallet_withdrawal_amount}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="referral_commission_amount">Referral Commission Amount (GHS)</Label>
                                        <Input
                                            id="referral_commission_amount"
                                            type="number" min="0" step="0.01"
                                            value={data.referral_commission_amount}
                                            onChange={(e) => setData('referral_commission_amount', parseFloat(e.target.value))}
                                            className={errors.referral_commission_amount ? 'border-red-500' : ''}
                                        />
                                        {errors.referral_commission_amount && <p className="text-sm text-red-500">{errors.referral_commission_amount}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="agent_upgrade_fee">Agent Upgrade Fee (GHS)</Label>
                                        <Input
                                            id="agent_upgrade_fee"
                                            type="number" min="0" step="0.01"
                                            value={data.agent_upgrade_fee}
                                            onChange={(e) => setData('agent_upgrade_fee', parseFloat(e.target.value))}
                                            className={errors.agent_upgrade_fee ? 'border-red-500' : ''}
                                        />
                                        {errors.agent_upgrade_fee && <p className="text-sm text-red-500">{errors.agent_upgrade_fee}</p>}
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    <h3 className="text-lg font-medium text-gray-900">Tutorial Video URLs</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="track_order_video_url">How to Track Order Video URL</Label>
                                            <Input
                                                id="track_order_video_url"
                                                type="url" placeholder="https://youtube.com/watch?v=..."
                                                value={data.track_order_video_url}
                                                onChange={(e) => setData('track_order_video_url', e.target.value)}
                                                className={errors.track_order_video_url ? 'border-red-500' : ''}
                                            />
                                            {errors.track_order_video_url && <p className="text-sm text-red-500">{errors.track_order_video_url}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="verify_topup_video_url">How to Verify Top Up Video URL</Label>
                                            <Input
                                                id="verify_topup_video_url"
                                                type="url" placeholder="https://youtube.com/watch?v=..."
                                                value={data.verify_topup_video_url}
                                                onChange={(e) => setData('verify_topup_video_url', e.target.value)}
                                                className={errors.verify_topup_video_url ? 'border-red-500' : ''}
                                            />
                                            {errors.verify_topup_video_url && <p className="text-sm text-red-500">{errors.verify_topup_video_url}</p>}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Updating...' : 'Update Settings'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Unverified Numbers */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Unverified Numbers ({unverifiedCount})</CardTitle>
                            <CardDescription>Numbers that were blocked by either restriction.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Input
                                    placeholder="Search phone number..."
                                    value={unverifiedSearch}
                                    onChange={(e) => setUnverifiedSearch(e.target.value)}
                                    className="max-w-xs"
                                />
                                <a
                                    href="/admin/unverified-numbers/download"
                                    className="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700"
                                >
                                    Download All as CSV
                                </a>
                            </div>
                            {unverifiedSearch && (() => {
                                const results = unverifiedNumbers.filter(n => n.phone_number.includes(unverifiedSearch));
                                return results.length > 0 ? (
                                    <div className="border rounded-md">
                                        <table className="w-full text-sm">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="text-left p-2">Phone Number</th>
                                                    <th className="text-left p-2">Restriction</th>
                                                    <th className="text-left p-2">Source</th>
                                                    <th className="text-left p-2">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {results.map((num) => (
                                                    <tr key={num.id} className="border-t">
                                                        <td className="p-2 font-mono">{num.phone_number}</td>
                                                        <td className="p-2 text-gray-600">{num.restriction_type}</td>
                                                        <td className="p-2 text-gray-600">{num.source}</td>
                                                        <td className="p-2 text-gray-500">{new Date(num.created_at).toLocaleDateString()}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <p className="text-gray-500 text-sm">No matching numbers found.</p>
                                );
                            })()}
                        </CardContent>
                    </Card>

                    {/* Previous Order Restriction */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Previous Order Restriction</CardTitle>
                            <CardDescription>
                                When enabled, only numbers that have previously placed an order can receive new orders.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <p className="font-medium">
                                    Status: {settings.previous_order_restriction_enabled
                                        ? <span className="text-red-600">Restriction ON</span>
                                        : <span className="text-green-600">Restriction OFF (all numbers allowed)</span>}
                                </p>
                                <div className="flex items-center gap-3">
                                    <a
                                        href="/admin/order-beneficiaries/download"
                                        className="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700"
                                    >
                                        Download Beneficiary Numbers
                                    </a>
                                    <Button
                                        onClick={togglePreviousOrderRestriction}
                                        variant={settings.previous_order_restriction_enabled ? 'destructive' : 'default'}
                                    >
                                        {settings.previous_order_restriction_enabled ? 'Turn OFF Restriction' : 'Turn ON Restriction'}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Number Restriction */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Number Restriction</CardTitle>
                            <CardDescription>
                                When enabled, only verified numbers in the list below can receive orders.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="flex items-center justify-between">
                                <p className="font-medium">
                                    Status: {settings.number_restriction_enabled
                                        ? <span className="text-red-600">Restriction ON</span>
                                        : <span className="text-green-600">Restriction OFF (all numbers allowed)</span>}
                                </p>
                                <Button
                                    onClick={toggleNumberRestriction}
                                    variant={settings.number_restriction_enabled ? 'destructive' : 'default'}
                                >
                                    {settings.number_restriction_enabled ? 'Turn OFF Restriction' : 'Turn ON Restriction'}
                                </Button>
                            </div>

                            <hr />

                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h4 className="font-medium">Verified Numbers ({verifiedNumbers.total})</h4>
                                    <a
                                        href="/admin/verified-numbers/download"
                                        className="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700"
                                    >
                                        Download All as CSV
                                    </a>
                                </div>

                                {/* Add single number */}
                                <form onSubmit={addNumber} className="flex gap-2">
                                    <Input
                                        placeholder="Enter 10-digit phone number"
                                        value={newNumber}
                                        onChange={(e) => setNewNumber(e.target.value.replace(/\D/g, '').slice(0, 10))}
                                        className="max-w-xs"
                                    />
                                    <Button type="submit" disabled={addingNumber || newNumber.length !== 10}>
                                        {addingNumber ? 'Adding...' : 'Add Number'}
                                    </Button>
                                </form>

                                {/* Import from file - completely separate form */}
                                <form onSubmit={importNumbers} className="flex gap-2">
                                    <Input
                                        type="file"
                                        accept=".csv"
                                        className="max-w-xs"
                                        onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                                    />
                                    <Button type="submit" disabled={importForm.processing || !importForm.data.file}>
                                        {importForm.processing ? 'Importing...' : 'Import from Excel/CSV'}
                                    </Button>
                                </form>

                                <div className="text-xs text-gray-500">
                                    <p className="font-medium mb-1">Expected file format (first column only):</p>
                                    <div className="border rounded w-32 text-center">
                                        <div className="bg-gray-100 px-3 py-1 font-semibold border-b">phone_number</div>
                                        <div className="px-3 py-1 border-b">0241234567</div>
                                        <div className="px-3 py-1 border-b">0551234567</div>
                                        <div className="px-3 py-1">0201234567</div>
                                    </div>
                                </div>

                                {/* Search */}
                                <form onSubmit={(e) => { e.preventDefault(); router.get(route('admin.settings'), { verified_search: search }, { preserveState: true, preserveScroll: true }); }} className="flex gap-2">
                                    <Input
                                        placeholder="Search phone number..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="max-w-xs"
                                    />
                                    <Button type="submit" variant="outline" size="sm">Search</Button>
                                    {search && <Button type="button" variant="ghost" size="sm" onClick={() => { setSearch(''); router.get(route('admin.settings'), {}, { preserveState: true, preserveScroll: true }); }}>Clear</Button>}
                                </form>

                                {verifiedNumbers.data.length > 0 ? (
                                    <div className="border rounded-md">
                                        <table className="w-full text-sm">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="text-left p-2">Phone Number</th>
                                                    <th className="text-left p-2">Added</th>
                                                    <th className="text-right p-2">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {verifiedNumbers.data.map((num) => (
                                                    <tr key={num.id} className="border-t">
                                                        <td className="p-2 font-mono">{num.phone_number}</td>
                                                        <td className="p-2 text-gray-500">{new Date(num.created_at).toLocaleDateString()}</td>
                                                        <td className="p-2 text-right">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-red-600 hover:text-red-800"
                                                                onClick={() => removeNumber(num.id)}
                                                            >
                                                                Remove
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                        <div className="flex items-center justify-between p-2 text-sm text-gray-500">
                                            <span>Showing {verifiedNumbers.data.length} of {verifiedNumbers.total}</span>
                                            <div className="flex gap-1">
                                                {verifiedNumbers.links.map((link, i) => (
                                                    link.url && <button key={i} onClick={() => router.get(link.url!, { verified_search: search })} className={`px-2 py-1 rounded ${link.active ? 'bg-gray-800 text-white' : 'hover:bg-gray-100'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-gray-500 text-sm">{search ? 'No matching numbers found.' : 'No verified numbers yet.'}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                </div>
            </div>
        </AdminLayout>
    );
}
