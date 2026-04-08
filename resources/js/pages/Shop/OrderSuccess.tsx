import React from 'react';
import { Head } from '@inertiajs/react';
import { CheckCircle, ShoppingBag, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Order {
    id: number;
    total: number;
    payment_reference: string;
    buyer_email: string;
    beneficiary_number: string;
    network: string;
    status: string;
    created_at: string;
    products: Array<{
        name: string;
        network: string;
    }>;
}

interface Agent {
    name: string;
}

interface Shop {
    name: string;
    slug: string;
    primary_color: string;
    background_color: string;
}

interface OrderSuccessProps {
    order: Order;
    agent: Agent;
    shop: Shop;
}

export default function OrderSuccess({ order, agent, shop }: OrderSuccessProps) {
    return (
        <>
            <Head title="Order Successful" />
            
            <div className="min-h-screen" style={{ backgroundColor: shop.background_color || '#F1F5F9' }}>
                {/* Header */}
                <div className="bg-white shadow-sm border-b" style={{ backgroundColor: shop.primary_color || '#3B82F6' }}>
                    <div className="container mx-auto px-4 py-6">
                        <div className="flex items-center justify-center">
                            <div className="flex items-center space-x-4">
                                <div className="bg-white bg-opacity-20 p-3 rounded-full">
                                    <ShoppingBag className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold text-white">{shop.name}</h1>
                                    <p className="text-white text-opacity-80">by {agent.name}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Success Content */}
                <div className="container mx-auto px-4 py-12">
                    <div className="max-w-2xl mx-auto">
                        {/* Success Icon */}
                        <div className="text-center mb-8">
                            <div className="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                                <CheckCircle className="h-12 w-12 text-green-600" />
                            </div>
                            <h2 className="text-3xl font-bold text-gray-900 mb-2">Order Successful!</h2>
                            <p className="text-gray-600">Your order has been placed and is being processed.</p>
                        </div>

                        {/* Order Details */}
                        <Card className="mb-8">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShoppingBag className="h-5 w-5" />
                                    Order Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-gray-600">Order ID</p>
                                        <p className="font-semibold">#{order.id}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Total Amount</p>
                                        <p className="font-semibold text-green-600">₵{parseFloat(order.total || 0).toFixed(2)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Network</p>
                                        <p className="font-semibold">{order.network}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Status</p>
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {order.status}
                                        </span>
                                    </div>
                                </div>
                                
                                <div className="border-t pt-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm text-gray-600">Beneficiary Number</p>
                                            <p className="font-semibold">{order.beneficiary_number}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-600">Email</p>
                                            <p className="font-semibold">{order.buyer_email}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="border-t pt-4">
                                    <p className="text-sm text-gray-600 mb-2">Products</p>
                                    {order.products?.map((product, index) => (
                                        <div key={index} className="flex justify-between items-center py-2">
                                            <span className="font-medium">{product.name}</span>
                                            <span className="text-sm text-gray-600">{product.network}</span>
                                        </div>
                                    ))}
                                </div>

                                <div className="border-t pt-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm text-gray-600">Payment Reference</p>
                                            <p className="font-mono text-sm">{order.payment_reference}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-600">Order Date</p>
                                            <p className="text-sm">{new Date(order.created_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Next Steps */}
                        <Card className="mb-8">
                            <CardHeader>
                                <CardTitle>What's Next?</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div className="flex items-start gap-3">
                                        <div className="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span className="text-xs font-semibold text-blue-600">1</span>
                                        </div>
                                        <div>
                                            <p className="font-medium">Processing</p>
                                            <p className="text-sm text-gray-600">Your order is being processed and will be fulfilled shortly.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div className="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span className="text-xs font-semibold text-blue-600">2</span>
                                        </div>
                                        <div>
                                            <p className="font-medium">Delivery</p>
                                            <p className="text-sm text-gray-600">Data/airtime will be delivered to {order.beneficiary_number} within minutes.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div className="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span className="text-xs font-semibold text-blue-600">3</span>
                                        </div>
                                        <div>
                                            <p className="font-medium">Confirmation</p>
                                            <p className="text-sm text-gray-600">You'll receive a confirmation email at {order.buyer_email}.</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Action Buttons */}
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <Button 
                                onClick={() => window.location.href = `/shop/${shop.slug}`}
                                className="flex items-center gap-2 text-white"
                                style={{ backgroundColor: shop.primary_color || '#3B82F6' }}
                            >
                                <ArrowRight className="h-4 w-4" />
                                Continue Shopping
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div className="bg-white border-t mt-16">
                    <div className="container mx-auto px-4 py-6">
                        <div className="text-center text-gray-600">
                            <p>Thank you for shopping with <span className="font-semibold">{shop.name}</span></p>
                            <p className="text-sm mt-1">Powered by <span className="font-semibold text-blue-600">ProDataWorld</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}