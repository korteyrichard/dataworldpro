import React from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Commission {
    id: number;
    order_id: number;
    product: { name: string };
    commission_amount: number;
    quantity: number;
    status: string;
    created_at: string;
}

interface CommissionsProps {
    auth: { user: any };
    commissions: Commission[];
}

export default function Commissions({ auth, commissions }: CommissionsProps) {
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'available': return 'bg-green-100 text-green-800';
            case 'withdrawn': return 'bg-gray-100 text-gray-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <DashboardLayout user={auth.user} header="Commissions">
            <Card>
                <CardHeader>
                    <CardTitle>Commission History</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-left p-2">Order ID</th>
                                    <th className="text-left p-2">Product</th>
                                    <th className="text-left p-2">Quantity</th>
                                    <th className="text-left p-2">Commission</th>
                                    <th className="text-left p-2">Status</th>
                                    <th className="text-left p-2">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {commissions.map((commission) => (
                                    <tr key={commission.id} className="border-b">
                                        <td className="p-2">#{commission.order_id}</td>
                                        <td className="p-2">{commission.product.name}</td>
                                        <td className="p-2">{commission.quantity}</td>
                                        <td className="p-2">₵{(commission.commission_amount * commission.quantity).toFixed(2)}</td>
                                        <td className="p-2">
                                            <Badge className={getStatusColor(commission.status)}>
                                                {commission.status}
                                            </Badge>
                                        </td>
                                        <td className="p-2">{new Date(commission.created_at).toLocaleDateString()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </DashboardLayout>
    );
}