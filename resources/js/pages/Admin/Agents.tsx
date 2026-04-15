import React from 'react';
import { AdminLayout } from '@/layouts/admin-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Store {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
    agent_shop?: {
        name: string;
        slug: string;
        is_active: boolean;
    };
    stats: {
        total_commissions: number;
        available_commissions: number;
        total_withdrawals: number;
        pending_withdrawals: number;
        commissions_count: number;
        withdrawals_count: number;
    };
}

interface StoresProps {
    auth: { user: any };
    agents: Store[];
    stats: {
        total_agents: number;
        total_admins: number;
        agents_with_shops: number;
        total_agent_earnings: number;
        total_pending_withdrawals: number;
    };
}

export default function Agents({ auth, agents, stats }: StoresProps) {
    return (
        <AdminLayout user={auth.user} header="Store Management">
            <div className="space-y-6">
                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Store Owners</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {stats?.total_agents || 0}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Active store owner accounts</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Active Stores</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {stats?.agents_with_shops || 0}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Stores created and active</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Admins</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                {stats?.total_admins || 0}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Admin accounts</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Store Earnings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-indigo-600">
                                ₵{Number(stats?.total_agent_earnings || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">All time store commissions</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Pending Withdrawals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">
                                ₵{Number(stats?.total_pending_withdrawals || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Awaiting approval</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Stores Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Store Owners & Admins</CardTitle>
                        <p className="text-sm text-gray-600">
                            Showing {agents?.length || 0} users
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">Store Owner</th>
                                        <th className="text-left p-2">Email</th>
                                        <th className="text-left p-2">Role</th>
                                        <th className="text-left p-2">Store</th>
                                        <th className="text-left p-2">Commissions</th>
                                        <th className="text-left p-2">Available</th>
                                        <th className="text-left p-2">Withdrawals</th>
                                        <th className="text-left p-2">Joined</th>
                                        <th className="text-left p-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {agents && agents.length > 0 ? (
                                        agents.map((store) => (
                                            <tr key={store.id} className="border-b hover:bg-gray-50">
                                                <td className="p-2">
                                                    <div>
                                                        <div className="font-medium">{store.name}</div>
                                                        <div className="text-sm text-gray-500">ID: {store.id}</div>
                                                    </div>
                                                </td>
                                                <td className="p-2">{store.email}</td>
                                                <td className="p-2">
                                                    <Badge variant={store.role === 'admin' ? 'destructive' : 'default'}>
                                                        {store.role === 'agent' ? 'Store Owner' : store.role}
                                                    </Badge>
                                                </td>
                                                <td className="p-2">
                                                    {store.agent_shop ? (
                                                        <div>
                                                            <div className="font-medium">{store.agent_shop.name}</div>
                                                            <div className="text-sm text-gray-500">/shop/{store.agent_shop.slug}</div>
                                                            <Badge 
                                                                variant={store.agent_shop.is_active ? 'default' : 'secondary'}
                                                                className="mt-1"
                                                            >
                                                                {store.agent_shop.is_active ? 'Active' : 'Inactive'}
                                                            </Badge>
                                                        </div>
                                                    ) : (
                                                        <span className="text-gray-400">No store</span>
                                                    )}
                                                </td>
                                                <td className="p-2">
                                                    <div>
                                                        <div className="font-medium">₵{Number(store.stats.total_commissions).toFixed(2)}</div>
                                                        <div className="text-sm text-gray-500">{store.stats.commissions_count} orders</div>
                                                    </div>
                                                </td>
                                                <td className="p-2">
                                                    <div className="font-medium text-green-600">
                                                        ₵{Number(store.stats.available_commissions).toFixed(2)}
                                                    </div>
                                                </td>
                                                <td className="p-2">
                                                    <div>
                                                        <div className="font-medium">₵{Number(store.stats.total_withdrawals).toFixed(2)}</div>
                                                        <div className="text-sm text-gray-500">{store.stats.withdrawals_count} requests</div>
                                                        {store.stats.pending_withdrawals > 0 && (
                                                            <div className="text-sm text-yellow-600">
                                                                ₵{Number(store.stats.pending_withdrawals).toFixed(2)} pending
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-2">{new Date(store.created_at).toLocaleDateString()}</td>
                                                <td className="p-2">
                                                    <div className="flex gap-2">
                                                        {store.agent_shop && (
                                                            <Button 
                                                                variant="outline" 
                                                                size="sm"
                                                                onClick={() => window.open(`/shop/${store.agent_shop?.slug}`, '_blank')}
                                                            >
                                                                View Store
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={9} className="p-4 text-center text-gray-500">
                                                No store owners found
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