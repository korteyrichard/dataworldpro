import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { ShoppingBag, ArrowLeft, CheckCircle, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Product {
    id: number;
    name: string;
    network: string;
    agent_price: string | number; // Can be string or number
    description?: string;
    size?: string;
}

interface Payment {
    reference: string;
    amount: number;
    email: string;
    beneficiary_number: string;
}

interface Shop {
    name: string;
    slug: string;
    primary_color: string;
    background_color: string;
}

interface Agent {
    name: string;
}

interface CreateOrderFromPaymentProps {
    shop: Shop;
    agent: Agent;
    payment: Payment;
    products: Product[];
}

export default function CreateOrderFromPayment({ shop, agent, payment, products }: CreateOrderFromPaymentProps) {
    const [selectedProduct, setSelectedProduct] = useState<number | null>(null);
    const { data, setData, post, processing, errors } = useForm({
        payment_reference: payment.reference,
        agent_product_id: '',
        beneficiary_number: payment.beneficiary_number
    });

    const handleProductSelect = (productId: number) => {
        setSelectedProduct(productId);
        setData('agent_product_id', productId.toString());
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedProduct) return;
        
        post(route('shop.create-order-from-payment', shop.slug));
    };

    return (
        <>
            <Head title={`Create Order - ${shop.name}`} />
            
            <div className="min-h-screen" style={{ backgroundColor: shop.background_color || '#F1F5F9' }}>
                {/* Header */}
                <div className="bg-white shadow-sm border-b" style={{ backgroundColor: shop.primary_color || '#3B82F6' }}>
                    <div className="container mx-auto px-4 py-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="bg-white bg-opacity-20 p-3 rounded-full">
                                    <ShoppingBag className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold text-white">{shop.name}</h1>
                                    <p className="text-white text-opacity-80">by {agent.name}</p>
                                </div>
                            </div>
                            <Button 
                                variant="outline" 
                                className="bg-white bg-opacity-20 text-white border-white border-opacity-50 hover:bg-white hover:bg-opacity-30"
                                onClick={() => window.location.href = `/shop/${shop.slug}`}
                            >
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Shop
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Content */}
                <div className="container mx-auto px-4 py-12">
                    <div className="max-w-4xl mx-auto">
                        {/* Payment Found Alert */}
                        <Card className="mb-8 border-green-200 bg-green-50">
                            <CardContent className="p-6">
                                <div className="flex items-start space-x-3">
                                    <CheckCircle className="h-6 w-6 text-green-600 mt-0.5" />
                                    <div>
                                        <h3 className="text-lg font-semibold text-green-800">Payment Found!</h3>
                                        <p className="text-green-700 mt-1">
                                            We found your payment of <strong>GHS {payment.amount.toFixed(2)}</strong> but no order was created.
                                        </p>
                                        <div className="mt-2 text-sm text-green-600">
                                            <p><strong>Payment Reference:</strong> {payment.reference}</p>
                                            <p><strong>Email:</strong> {payment.email}</p>
                                            <p><strong>Phone Number:</strong> {payment.beneficiary_number}</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Product Selection */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <AlertCircle className="h-5 w-5 text-blue-600" />
                                    <span>Select Your Product</span>
                                </CardTitle>
                                <p className="text-gray-600">
                                    Choose the product you intended to purchase. Only products matching your payment amount are shown.
                                </p>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid gap-4">
                                        {products.map((product) => (
                                            <div
                                                key={product.id}
                                                className={`border rounded-lg p-4 cursor-pointer transition-all ${
                                                    selectedProduct === product.id
                                                        ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200'
                                                        : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                                }`}
                                                onClick={() => handleProductSelect(product.id)}
                                            >
                                                <div className="flex items-center space-x-3">
                                                    <input
                                                        type="radio"
                                                        name="product"
                                                        value={product.id}
                                                        checked={selectedProduct === product.id}
                                                        onChange={() => handleProductSelect(product.id)}
                                                        className="h-4 w-4 text-blue-600"
                                                    />
                                                    <div className="flex-1">
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <h3 className="font-semibold text-gray-900">
                                                                    {product.name}
                                                                    {product.size && (
                                                                        <span className="ml-2 text-sm text-gray-600">
                                                                            ({product.size})
                                                                        </span>
                                                                    )}
                                                                </h3>
                                                                <p className="text-sm text-gray-600">
                                                                    Network: {product.network}
                                                                </p>
                                                                {product.description && (
                                                                    <p className="text-sm text-gray-500 mt-1">
                                                                        {product.description}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="text-lg font-bold text-green-600">
                                                                    GHS {parseFloat(product.agent_price.toString()).toFixed(2)}
                                                                </p>
                                                                <p className="text-xs text-gray-500">
                                                                    Matches your payment
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {errors.agent_product_id && (
                                        <p className="text-red-500 text-sm">{errors.agent_product_id}</p>
                                    )}

                                    <div className="flex justify-end space-x-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => window.location.href = `/shop/${shop.slug}`}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing || !selectedProduct}
                                            className="text-white"
                                            style={{ backgroundColor: shop.primary_color || '#3B82F6' }}
                                        >
                                            {processing ? 'Creating Order...' : 'Create Order'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Help Section */}
                        <Card className="mt-6">
                            <CardHeader>
                                <CardTitle className="text-lg">What happens next?</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <p className="font-medium text-sm">✅ No additional payment required</p>
                                    <p className="text-sm text-gray-600">
                                        Your payment has already been processed successfully.
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-sm">📱 Order will be processed</p>
                                    <p className="text-sm text-gray-600">
                                        Once you select a product, your order will be created and processed immediately.
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-sm">🎯 Data will be delivered</p>
                                    <p className="text-sm text-gray-600">
                                        The selected data bundle will be sent to {payment.beneficiary_number}.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Footer */}
                <div className="bg-white border-t mt-16">
                    <div className="container mx-auto px-4 py-6">
                        <div className="text-center text-gray-600">
                            <p>Order creation for <span className="font-semibold">{shop.name}</span></p>
                            <p className="text-sm mt-1">Powered by <span className="font-semibold text-blue-600">ProDataWorld</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}