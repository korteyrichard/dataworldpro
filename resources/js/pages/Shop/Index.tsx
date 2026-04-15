import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Trash2, Plus, Store, Edit } from 'lucide-react';

export default function ShopIndex({ auth, shop, products, availableProducts }) {
    const [isCreating, setIsCreating] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [isAddingProduct, setIsAddingProduct] = useState(false);

    const createForm = useForm({
        name: '',
        primary_color: '#3B82F6',
        background_color: '#F8FAFC'
    });

    const updateForm = useForm({
        name: shop?.name || '',
        primary_color: shop?.primary_color || '#3B82F6',
        background_color: shop?.background_color || '#F8FAFC'
    });

    const productForm = useForm({
        product_id: '',
        variant_id: '',
        agent_price: ''
    });

    const handleCreateShop = (e) => {
        e.preventDefault();
        createForm.post(route('shop.create'), {
            onSuccess: () => {
                setIsCreating(false);
                createForm.reset();
            }
        });
    };

    const handleUpdateShop = (e) => {
        e.preventDefault();
        updateForm.put(route('shop.update'), {
            onSuccess: () => {
                setIsEditing(false);
            }
        });
    };

    const handleAddProduct = (e) => {
        e.preventDefault();
        productForm.post(route('shop.products.add'), {
            onSuccess: () => {
                setIsAddingProduct(false);
                productForm.reset();
            }
        });
    };

    const handleRemoveProduct = (productId) => {
        if (confirm('Are you sure you want to remove this product?')) {
            router.delete(route('shop.products.remove', productId));
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">My Shop</h2>}
        >
            <Head title="My Shop" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {!shop ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Store className="h-5 w-5" />
                                    Create Your Shop
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!isCreating ? (
                                    <div className="text-center py-8">
                                        <p className="text-gray-600 mb-4">
                                            You don't have a shop yet. Create one to start selling!
                                        </p>
                                        <Button onClick={() => setIsCreating(true)}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Create Shop
                                        </Button>
                                    </div>
                                ) : (
                                    <form onSubmit={handleCreateShop} className="space-y-4">
                                        <div>
                                            <Label htmlFor="name">Shop Name</Label>
                                            <Input
                                                id="name"
                                                value={createForm.data.name}
                                                onChange={(e) => createForm.setData('name', e.target.value)}
                                                required
                                            />
                                            {createForm.errors.name && (
                                                <p className="text-red-500 text-sm mt-1">{createForm.errors.name}</p>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="primary_color">Primary Color</Label>
                                                <Input
                                                    id="primary_color"
                                                    type="color"
                                                    value={createForm.data.primary_color}
                                                    onChange={(e) => createForm.setData('primary_color', e.target.value)}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="background_color">Background Color</Label>
                                                <Input
                                                    id="background_color"
                                                    type="color"
                                                    value={createForm.data.background_color}
                                                    onChange={(e) => createForm.setData('background_color', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button type="submit" disabled={createForm.processing}>
                                                Create Shop
                                            </Button>
                                            <Button 
                                                type="button" 
                                                variant="outline" 
                                                onClick={() => setIsCreating(false)}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-6">
                            {/* Shop Details */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <Store className="h-5 w-5" />
                                            {shop.name}
                                        </CardTitle>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={shop.is_active ? 'default' : 'secondary'}>
                                                {shop.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                            <Button 
                                                size="sm" 
                                                variant="outline"
                                                onClick={() => setIsEditing(true)}
                                            >
                                                <Edit className="h-4 w-4 mr-2" />
                                                Edit
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm text-gray-600">Shop URL</p>
                                            <p className="font-mono text-sm">{shop.public_url}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-600">Slug</p>
                                            <p className="font-mono text-sm">{shop.slug}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Products */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>Products ({products.length})</CardTitle>
                                        <Button onClick={() => setIsAddingProduct(true)}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Product
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {products.length === 0 ? (
                                        <p className="text-gray-600 text-center py-8">
                                            No products added yet. Add some products to start selling!
                                        </p>
                                    ) : (
                                        <div className="grid gap-4">
                                            {products.map((product) => (
                                                <div key={product.id} className="flex items-center justify-between p-4 border rounded-lg">
                                                    <div>
                                                        <h4 className="font-medium">{product.product.name}</h4>
                                                        <p className="text-sm text-gray-600">{product.product.network}</p>
                                                        <p className="text-sm font-medium text-green-600">
                                                            GHS {product.agent_price}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => handleRemoveProduct(product.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}