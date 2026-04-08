import React from 'react';
import { AdminLayout } from '@/layouts/admin-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Agent {
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

interface AgentsProps {
    auth: { user: any };
    agents: Agent[];
    stats: {
        total_agents: number;
        total_admins: number;
        agents_with_shops: number;
        total_agent_earnings: number;
        total_pending_withdrawals: number;
    };
}

export default function Agents({ auth, agents, stats }: AgentsProps) {
    return (
        <AdminLayout user={auth.user} header="Agent Management">
            <div className="space-y-6">
                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Agents</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {stats?.total_agents || 0}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Active agent accounts</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Agents with Shops</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {stats?.agents_with_shops || 0}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Have created shops</p>
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
                            <CardTitle className="text-sm font-medium text-gray-600">Total Agent Earnings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-indigo-600">
                                ₵{Number(stats?.total_agent_earnings || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500 mt-1">All time commissions</p>
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

                {/* Agents Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Agents & Admins</CardTitle>
                        <p className="text-sm text-gray-600">
                            Showing {agents?.length || 0} users
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">Name</th>
                                        <th className="text-left p-2">Email</th>
                                        <th className="text-left p-2">Role</th>
                                        <th className="text-left p-2">Shop</th>
                                        <th className="text-left p-2">Commissions</th>
                                        <th className="text-left p-2">Available</th>
                                        <th className="text-left p-2">Withdrawals</th>
                                        <th className="text-left p-2">Joined</th>
                                        <th className="text-left p-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {agents && agents.length > 0 ? (
                                        agents.map((agent) => (
                                            <tr key={agent.id} className="border-b hover:bg-gray-50">
                                                <td className="p-2">
                                                    <div>
                                                        <div className="font-medium">{agent.name}</div>
                                                        <div className="text-sm text-gray-500">ID: {agent.id}</div>
                                                    </div>
                                                </td>
                                                <td className="p-2">{agent.email}</td>
                                                <td className="p-2">
                                                    <Badge variant={agent.role === 'admin' ? 'destructive' : 'default'}>
                                                        {agent.role}
                                                    </Badge>
                                                </td>
                                                <td className="p-2">
                                                    {agent.agent_shop ? (
                                                        <div>
                                                            <div className="font-medium">{agent.agent_shop.name}</div>
                                                            <div className="text-sm text-gray-500">/shop/{agent.agent_shop.slug}</div>
                                                            <Badge 
                                                                variant={agent.agent_shop.is_active ? 'default' : 'secondary'}
                                                                className="mt-1"
                                                            >
                                                                {agent.agent_shop.is_active ? 'Active' : 'Inactive'}
                                                            </Badge>
                                                        </div>
                                                    ) : (
                                                        <span className="text-gray-400">No shop</span>
                                                    )}
                                                </td>
                                                <td className="p-2">
                                                    <div>
                                                        <div className="font-medium">₵{Number(agent.stats.total_commissions).toFixed(2)}</div>
                                                        <div className="text-sm text-gray-500">{agent.stats.commissions_count} orders</div>
                                                    </div>
                                                </td>
                                                <td className="p-2">
                                                    <div className="font-medium text-green-600">
                                                        ₵{Number(agent.stats.available_commissions).toFixed(2)}
                                                    </div>
                                                </td>
                                                <td className="p-2">
                                                    <div>
                                                        <div className="font-medium">₵{Number(agent.stats.total_withdrawals).toFixed(2)}</div>
                                                        <div className="text-sm text-gray-500">{agent.stats.withdrawals_count} requests</div>
                                                        {agent.stats.pending_withdrawals > 0 && (
                                                            <div className="text-sm text-yellow-600">
                                                                ₵{Number(agent.stats.pending_withdrawals).toFixed(2)} pending
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-2">{new Date(agent.created_at).toLocaleDateString()}</td>
                                                <td className="p-2">
                                                    <div className="flex gap-2">
                                                        {agent.agent_shop && (
                                                            <Button 
                                                                variant="outline" 
                                                                size="sm"
                                                                onClick={() => window.open(`/shop/${agent.agent_shop?.slug}`, '_blank')}
                                                            >
                                                                View Shop
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={9} className="p-4 text-center text-gray-500">
                                                No agents found
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