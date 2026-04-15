import React, { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useToast } from '@/components/ui/use-toast';

interface Withdrawal {
    id: number;
    agent: { 
        name: string; 
        email: string;
        shop?: {
            name: string;
            slug: string;
            is_active: boolean;
        };
    };
    amount: number;
    phone_number?: string;
    network?: string;
    mobile_money_name?: string;
    withdrawal_fee: number;
    net_amount: number;
    status: string;
    notes: string;
    created_at: string;
}

interface AdminWithdrawalsProps {
    auth: { user: any };
    withdrawals: {
        data: Withdrawal[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export default function AdminWithdrawals({ auth, withdrawals }: AdminWithdrawalsProps) {
    const { toast } = useToast();
    const { flash } = usePage().props as any;

    // Handle flash messages
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

    const handleProcess = (withdrawalId: number, action: 'approve' | 'reject', notes?: string) => {
        router.post(route('admin.withdrawals.process', withdrawalId), {
            action,
            notes
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'approved': return 'bg-green-100 text-green-800';
            case 'rejected': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const withdrawalsData = withdrawals?.data || [];

    return (
        <AdminLayout user={auth.user} header="Shop Withdrawal Management">
            <Card>
                <CardHeader>
                    <CardTitle>Shop Withdrawal Requests</CardTitle>
                    <p className="text-sm text-gray-600">
                        Showing {withdrawalsData.length} of {withdrawals?.total || 0} shop withdrawals
                    </p>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-left p-2">Shop Owner</th>
                                    <th className="text-left p-2">Shop Name</th>
                                    <th className="text-left p-2">Amount</th>
                                    <th className="text-left p-2">Mobile Money</th>
                                    <th className="text-left p-2">Net Amount</th>
                                    <th className="text-left p-2">Status</th>
                                    <th className="text-left p-2">Date</th>
                                    <th className="text-left p-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {withdrawalsData.length > 0 ? (
                                    withdrawalsData.map((withdrawal) => (
                                        <tr key={withdrawal.id} className="border-b">
                                            <td className="p-2">
                                                <div>
                                                    <div>{withdrawal.agent?.name || 'N/A'}</div>
                                                    <div className="text-sm text-gray-500">{withdrawal.agent?.email || 'N/A'}</div>
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                {withdrawal.agent?.shop ? (
                                                    <div>
                                                        <div className="font-medium">{withdrawal.agent.shop.name}</div>
                                                        <div className="text-sm text-gray-500">/{withdrawal.agent.shop.slug}</div>
                                                        <Badge className={withdrawal.agent.shop.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}>
                                                            {withdrawal.agent.shop.is_active ? 'Active' : 'Inactive'}
                                                        </Badge>
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-400">No Shop</span>
                                                )}
                                            </td>
                                            <td className="p-2">₵{Number(withdrawal.amount || 0).toFixed(2)}</td>
                                            <td className="p-2">
                                                {withdrawal.network && withdrawal.phone_number ? (
                                                    <div className="text-sm">
                                                        <div className="font-medium">{withdrawal.network}</div>
                                                        <div className="text-gray-600">{withdrawal.phone_number}</div>
                                                        {withdrawal.mobile_money_name && (
                                                            <div className="text-gray-500 text-xs">{withdrawal.mobile_money_name}</div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="p-2 font-medium">₵{Number(withdrawal.net_amount || withdrawal.amount || 0).toFixed(2)}</td>
                                            <td className="p-2">
                                                <Badge className={getStatusColor(withdrawal.status)}>
                                                    {withdrawal.status}
                                                </Badge>
                                            </td>
                                            <td className="p-2">{new Date(withdrawal.created_at).toLocaleDateString()}</td>
                                            <td className="p-2">
                                                {withdrawal.status === 'pending' && (
                                                    <div className="flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="default"
                                                            onClick={() => handleProcess(withdrawal.id, 'approve')}
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() => handleProcess(withdrawal.id, 'reject')}
                                                        >
                                                            Reject
                                                        </Button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="p-4 text-center text-gray-500">
                                            No shop withdrawal requests found
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}