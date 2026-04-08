import { useState, useEffect } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/components/ui/use-toast';

interface Settings {
    minimum_withdrawal_amount: number;
    referral_commission_amount: number;
    agent_upgrade_fee: number;
    track_order_video_url: string;
    verify_topup_video_url: string;
}

interface Props {
    auth: { user: any };
    settings: Settings;
}

export default function Settings({ auth, settings }: Props) {
    const { toast } = useToast();
    const { flash } = usePage().props as any;
    const { data, setData, post, processing, errors } = useForm({
        minimum_withdrawal_amount: settings.minimum_withdrawal_amount,
        referral_commission_amount: settings.referral_commission_amount,
        agent_upgrade_fee: settings.agent_upgrade_fee,
        track_order_video_url: settings.track_order_video_url || '',
        verify_topup_video_url: settings.verify_topup_video_url || '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast({
                title: "Success",
                description: flash.success,
            });
        }
        if (flash?.error) {
            toast({
                title: "Error",
                description: flash.error,
                variant: "destructive",
            });
        }
    }, [flash, toast]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.update'));
    };

    return (
        <AdminLayout
            user={auth.user}
            header="Agent Settings"
        >
            <Head title="Agent Settings" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Agent System Settings</CardTitle>
                            <CardDescription>
                                Configure settings for the agent system including withdrawal limits, referral amounts, upgrade fees, and tutorial video URLs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="minimum_withdrawal_amount">
                                            Minimum Withdrawal Amount (GHS)
                                        </Label>
                                        <Input
                                            id="minimum_withdrawal_amount"
                                            type="number"
                                            min="1"
                                            step="0.01"
                                            value={data.minimum_withdrawal_amount}
                                            onChange={(e) => setData('minimum_withdrawal_amount', parseFloat(e.target.value))}
                                            className={errors.minimum_withdrawal_amount ? 'border-red-500' : ''}
                                        />
                                        {errors.minimum_withdrawal_amount && (
                                            <p className="text-sm text-red-500">{errors.minimum_withdrawal_amount}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="referral_commission_amount">
                                            Referral Commission Amount (GHS)
                                        </Label>
                                        <Input
                                            id="referral_commission_amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={data.referral_commission_amount}
                                            onChange={(e) => setData('referral_commission_amount', parseFloat(e.target.value))}
                                            className={errors.referral_commission_amount ? 'border-red-500' : ''}
                                        />
                                        {errors.referral_commission_amount && (
                                            <p className="text-sm text-red-500">{errors.referral_commission_amount}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="agent_upgrade_fee">
                                            Agent Upgrade Fee (GHS)
                                        </Label>
                                        <Input
                                            id="agent_upgrade_fee"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={data.agent_upgrade_fee}
                                            onChange={(e) => setData('agent_upgrade_fee', parseFloat(e.target.value))}
                                            className={errors.agent_upgrade_fee ? 'border-red-500' : ''}
                                        />
                                        {errors.agent_upgrade_fee && (
                                            <p className="text-sm text-red-500">{errors.agent_upgrade_fee}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <h3 className="text-lg font-medium text-gray-900">Tutorial Video URLs</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="track_order_video_url">
                                                How to Track Order Video URL
                                            </Label>
                                            <Input
                                                id="track_order_video_url"
                                                type="url"
                                                placeholder="https://youtube.com/watch?v=..."
                                                value={data.track_order_video_url}
                                                onChange={(e) => setData('track_order_video_url', e.target.value)}
                                                className={errors.track_order_video_url ? 'border-red-500' : ''}
                                            />
                                            {errors.track_order_video_url && (
                                                <p className="text-sm text-red-500">{errors.track_order_video_url}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="verify_topup_video_url">
                                                How to Verify Top Up Video URL
                                            </Label>
                                            <Input
                                                id="verify_topup_video_url"
                                                type="url"
                                                placeholder="https://youtube.com/watch?v=..."
                                                value={data.verify_topup_video_url}
                                                onChange={(e) => setData('verify_topup_video_url', e.target.value)}
                                                className={errors.verify_topup_video_url ? 'border-red-500' : ''}
                                            />
                                            {errors.verify_topup_video_url && (
                                                <p className="text-sm text-red-500">{errors.verify_topup_video_url}</p>
                                            )}
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
                </div>
            </div>
        </AdminLayout>
    );
}