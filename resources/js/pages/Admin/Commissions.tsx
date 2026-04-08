import React from 'react';
import { AdminLayout } from '@/layouts/admin-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Commission {
    id: number;
    agent: { name: string; email: string };
    order_id: number;
    product: { name: string };
    commission_amount: number;
    quantity: number;
    status: string;
    created_at: string;
}

interface AdminCommissionsProps {
    auth: { user: any };
    commissions: {
        data: Commission[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: {
        total_commissions: number;
        pending_commissions: number;
        available_commissions: number;
        withdrawn_commissions: number;
        total_referral_commissions: number;
        available_referral_commissions: number;
    };
}

export default function AdminCommissions({ auth, commissions, stats }: AdminCommissionsProps) {
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'available': return 'bg-green-100 text-green-800';
            case 'withdrawn': return 'bg-gray-100 text-gray-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const commissionsData = commissions?.data || [];

    return (
        <AdminLayout user={auth.user} header="Commission Management">
            <div className="space-y-6">
                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Commissions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                ₵{Number(stats?.total_commissions || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">All time earnings</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Available Commissions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                ₵{Number(stats?.available_commissions || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Ready for withdrawal</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Withdrawn Commissions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-gray-600">
                                ₵{Number(stats?.withdrawn_commissions || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Already paid out</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Referral Commissions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                ₵{Number(stats?.total_referral_commissions || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">All referral earnings</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Available Referral Commissions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-indigo-600">
                                ₵{Number(stats?.available_referral_commissions || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Ready for withdrawal</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Commissions Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Commissions</CardTitle>
                        <p className="text-sm text-gray-600">
                            Showing {commissionsData.length} of {commissions?.total || 0} commissions
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">Agent</th>
                                        <th className="text-left p-2">Order ID</th>
                                        <th className="text-left p-2">Product</th>
                                        <th className="text-left p-2">Quantity</th>
                                        <th className="text-left p-2">Commission</th>
                                        <th className="text-left p-2">Status</th>
                                        <th className="text-left p-2">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {commissionsData.length > 0 ? (
                                        commissionsData.map((commission) => (
                                            <tr key={commission.id} className="border-b">
                                                <td className="p-2">
                                                    <div>
                                                        <div>{commission.agent?.name || 'N/A'}</div>
                                                        <div className="text-sm text-gray-500">{commission.agent?.email || 'N/A'}</div>
                                                    </div>
                                                </td>
                                                <td className="p-2">#{commission.order_id}</td>
                                                <td className="p-2">{commission.product?.name || 'N/A'}</td>
                                                <td className="p-2">{commission.quantity}</td>
                                                <td className="p-2">₵{(commission.commission_amount * commission.quantity).toFixed(2)}</td>
                                                <td className="p-2">
                                                    <Badge className={getStatusColor(commission.status)}>
                                                        {commission.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-2">{new Date(commission.created_at).toLocaleDateString()}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={7} className="p-4 text-center text-gray-500">
                                                No commissions found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}