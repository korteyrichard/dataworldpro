import React, { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface AvailableProduct {
    id: number;
    name: string;
    product_type: string;
    network: string;
    variants: Array<{
        id: number;
        name: string;
        price: number;
    }>;
}

interface Shop {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
}

interface Product {
    id: number;
    product: { name: string };
    variant: { name: string; price: number };
    agent_price: number;
    commission_amount: number;
}

interface ShopProps {
    auth: { user: any };
    shop: Shop | null;
    products: Product[];
    availableProducts: AvailableProduct[];
    domains: {
        main: string;
        second: string;
    };
}

export default function Shop({ auth, shop, products, availableProducts, domains }: ShopProps) {
    const [shopName, setShopName] = useState('');
    const [primaryColor, setPrimaryColor] = useState('#3B82F6');
    const [backgroundColor, setBackgroundColor] = useState('#F1F5F9');
    const [loading, setLoading] = useState(false);
    const [editing, setEditing] = useState(false);
    const [editName, setEditName] = useState(shop?.name || '');
    const [editPrimaryColor, setEditPrimaryColor] = useState(shop?.primary_color || '#3B82F6');
    const [editBackgroundColor, setEditBackgroundColor] = useState(shop?.background_color || '#F1F5F9');
    const [showAddProduct, setShowAddProduct] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState('');
    const [selectedVariant, setSelectedVariant] = useState('');
    const [agentPrice, setAgentPrice] = useState('');

    const handleCreateShop = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        
        router.post(route('agent.shop.create'), {
            name: shopName,
            primary_color: primaryColor,
            background_color: backgroundColor
        }, {
            onFinish: () => {
                setLoading(false);
                setShopName('');
            }
        });
    };

    const [deletingProduct, setDeletingProduct] = useState<number | null>(null);

    const handleEditShop = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        
        router.put(route('agent.shop.update'), {
            name: editName,
            primary_color: editPrimaryColor,
            background_color: editBackgroundColor
        }, {
            onFinish: () => {
                setLoading(false);
                setEditing(false);
            }
        });
    };

    const handleDeleteProduct = (productId: number) => {
        if (confirm('Are you sure you want to remove this product from your shop?')) {
            setDeletingProduct(productId);
            router.delete(route('agent.products.remove', productId), {
                onFinish: () => {
                    setDeletingProduct(null);
                }
            });
        }
    };

    const handleAddProduct = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        
        router.post(route('agent.products.add'), {
            product_id: selectedProduct,
            variant_id: selectedVariant,
            agent_price: parseFloat(agentPrice)
        }, {
            onFinish: () => {
                setLoading(false);
                setShowAddProduct(false);
                setSelectedProduct('');
                setSelectedVariant('');
                setAgentPrice('');
            }
        });
    };

    const selectedProductData = availableProducts?.find(p => p.id.toString() === selectedProduct);
    const selectedVariantData = selectedProductData?.variants.find(v => v.id.toString() === selectedVariant);
    const basePrice = parseFloat(selectedVariantData?.price || 0);

    return (
        <DashboardLayout user={auth.user} header="My Shop">
            <div className="space-y-6">
                {!shop ? (
                    /* Create Shop Form */
                    <Card>
                        <CardHeader>
                            <CardTitle>Create Your Shop</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleCreateShop} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium mb-2">Shop Name</label>
                                    <Input
                                        type="text"
                                        placeholder="Enter your shop name"
                                        value={shopName}
                                        onChange={(e) => setShopName(e.target.value)}
                                        required
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium mb-2">Primary Color (Buttons/Header)</label>
                                        <input
                                            type="color"
                                            value={primaryColor}
                                            onChange={(e) => setPrimaryColor(e.target.value)}
                                            className="w-full h-10 border rounded cursor-pointer"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium mb-2">Background Color</label>
                                        <input
                                            type="color"
                                            value={backgroundColor}
                                            onChange={(e) => setBackgroundColor(e.target.value)}
                                            className="w-full h-10 border rounded cursor-pointer"
                                        />
                                    </div>
                                </div>
                                <Button type="submit" disabled={loading || !shopName}>
                                    {loading ? 'Creating...' : 'Create Shop'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Shop Info */}
                        <Card>
                            <CardHeader>
                                <div className="flex justify-between items-center">
                                    <CardTitle>Shop Information</CardTitle>
                                    <Button 
                                        variant="outline" 
                                        onClick={() => setEditing(!editing)}
                                    >
                                        {editing ? 'Cancel' : 'Edit Shop'}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {editing ? (
                                    <form onSubmit={handleEditShop} className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium mb-2">Shop Name</label>
                                            <Input
                                                type="text"
                                                value={editName}
                                                onChange={(e) => setEditName(e.target.value)}
                                                required
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium mb-2">Primary Color</label>
                                                <input
                                                    type="color"
                                                    value={editPrimaryColor}
                                                    onChange={(e) => setEditPrimaryColor(e.target.value)}
                                                    className="w-full h-10 border rounded cursor-pointer"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium mb-2">Background Color</label>
                                                <input
                                                    type="color"
                                                    value={editBackgroundColor}
                                                    onChange={(e) => setEditBackgroundColor(e.target.value)}
                                                    className="w-full h-10 border rounded cursor-pointer"
                                                />
                                            </div>
                                        </div>
                                        <Button type="submit" disabled={loading}>
                                            {loading ? 'Updating...' : 'Update Shop'}
                                        </Button>
                                    </form>
                                ) : (
                                    <div className="space-y-4">
                                        <p className="text-gray-900 dark:text-gray-100"><strong>Shop Name:</strong> {shop.name}</p>
                                        <div>
                                            <p className="text-gray-900 dark:text-gray-100"><strong>Shop URLs:</strong></p>
                                            <div className="space-y-3 mt-2">
                                                {/* Main Domain URL */}
                                                <div>
                                                    <label className="text-sm text-gray-600 dark:text-gray-400">Main Domain:</label>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <input 
                                                            type="text" 
                                                            value={`${window.location.protocol}//${domains.main || window.location.host}/shop/${shop.slug}`}
                                                            readOnly
                                                            className="flex-1 p-2 border rounded bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
                                                        />
                                                        <button 
                                                            onClick={() => navigator.clipboard.writeText(`${window.location.protocol}//${domains.main || window.location.host}/shop/${shop.slug}`)}
                                                            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                                        >
                                                            Copy
                                                        </button>
                                                    </div>
                                                </div>
                                                {/* Second Domain URL */}
                                                {domains.second && (
                                                <div>
                                                    <label className="text-sm text-gray-600 dark:text-gray-400">Store Domain:</label>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <input 
                                                            type="text" 
                                                            value={`${window.location.protocol}//${domains.second}/shop/${shop.slug}`}
                                                            readOnly
                                                            className="flex-1 p-2 border rounded bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
                                                        />
                                                        <button 
                                                            onClick={() => navigator.clipboard.writeText(`${window.location.protocol}//${domains.second}/shop/${shop.slug}`)}
                                                            className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                                                        >
                                                            Copy
                                                        </button>
                                                    </div>
                                                </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <p className="text-gray-900 dark:text-gray-100"><strong>Primary Color:</strong></p>
                                                <div className="flex items-center gap-2 mt-1">
                                                    <div 
                                                        className="w-8 h-8 rounded border"
                                                        style={{ backgroundColor: shop.primary_color }}
                                                    ></div>
                                                    <span className="text-sm text-gray-600 dark:text-gray-400">{shop.primary_color}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <p className="text-gray-900 dark:text-gray-100"><strong>Background Color:</strong></p>
                                                <div className="flex items-center gap-2 mt-1">
                                                    <div 
                                                        className="w-8 h-8 rounded border"
                                                        style={{ backgroundColor: shop.background_color }}
                                                    ></div>
                                                    <span className="text-sm text-gray-600 dark:text-gray-400">{shop.background_color}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <p className="text-gray-900 dark:text-gray-100"><strong>Status:</strong> 
                                            <span className={shop.is_active ? 'text-green-600' : 'text-red-600'}>
                                                {shop.is_active ? ' Active' : ' Inactive'}
                                            </span>
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Shop Products */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Shop Products ({products.length})</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {products.length > 0 ? (
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b border-gray-200 dark:border-gray-600">
                                                    <th className="text-left p-2 text-gray-900 dark:text-gray-100">Product</th>
                                                    <th className="text-left p-2 text-gray-900 dark:text-gray-100">Variant</th>
                                                    <th className="text-left p-2 text-gray-900 dark:text-gray-100">Base Price</th>
                                                    <th className="text-left p-2 text-gray-900 dark:text-gray-100">Your Price</th>
                                                    <th className="text-left p-2 text-gray-900 dark:text-gray-100">Commission</th>
                                                    <th className="text-left p-2 text-gray-900 dark:text-gray-100">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {products.map((product) => (
                                                    <tr key={product.id} className="border-b border-gray-200 dark:border-gray-600">
                                                        <td className="p-2 text-gray-900 dark:text-gray-100">{product.product.name}</td>
                                                        <td className="p-2 text-gray-900 dark:text-gray-100">{product.variant.name}</td>
                                                        <td className="p-2 text-gray-900 dark:text-gray-100">₵{parseFloat(product.variant.price || 0).toFixed(2)}</td>
                                                        <td className="p-2 text-gray-900 dark:text-gray-100">₵{parseFloat(product.agent_price || 0).toFixed(2)}</td>
                                                        <td className="p-2 text-gray-900 dark:text-gray-100">₵{parseFloat(product.commission_amount || 0).toFixed(2)}</td>
                                                        <td className="p-2">
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() => handleDeleteProduct(product.id)}
                                                                disabled={deletingProduct === product.id}
                                                                className="flex items-center gap-1"
                                                            >
                                                                <Trash2 className="h-3 w-3" />
                                                                {deletingProduct === product.id ? 'Removing...' : 'Remove'}
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <p className="text-gray-500 dark:text-gray-400 mb-4">No products added to your shop yet.</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}
                
                        {/* Add Product Form */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Add Product to Shop</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleAddProduct} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium mb-2">Select Product Variant</label>
                                        <div className="max-h-64 overflow-y-auto border rounded p-2 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600">
                                            {availableProducts?.length > 0 ? (
                                                availableProducts.map(product => 
                                                    product.variants.map(variant => (
                                                        <div key={`${product.id}-${variant.id}`} className="flex items-center justify-between p-2 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                                                            <div className="flex items-center space-x-3">
                                                                <input
                                                                    type="radio"
                                                                    name="product_variant"
                                                                    value={`${product.id}-${variant.id}`}
                                                                    onChange={() => {
                                                                        setSelectedProduct(product.id.toString());
                                                                        setSelectedVariant(variant.id.toString());
                                                                        setAgentPrice(parseFloat(variant.price || 0).toString());
                                                                    }}
                                                                    className="w-4 h-4"
                                                                />
                                                                <div>
                                                                    <p className="font-medium text-gray-900 dark:text-gray-100">{product.name}</p>
                                                                    <p className="text-sm text-gray-600 dark:text-gray-400">{variant.name}</p>
                                                                    <div className="flex gap-2 mt-1">
                                                                        <span className="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                                                                            {product.network}
                                                                        </span>
                                                                        <span className="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-1 rounded">
                                                                            {product.product_type.replace('_', ' ').toUpperCase()}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-green-600 dark:text-green-400">₵{parseFloat(variant.price || 0).toFixed(2)}</p>
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">Base Price</p>
                                                            </div>
                                                        </div>
                                                    ))
                                                )
                                            ) : (
                                                <div className="text-center py-8 text-gray-500 dark:text-gray-400">
                                                    <p>No products available for your role.</p>
                                                    <p className="text-sm mt-1">Contact admin if you think this is an error.</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {selectedVariantData && (
                                        <div>
                                            <label className="block text-sm font-medium mb-2">
                                                Your Selling Price (Base: ₵{basePrice.toFixed(2)})
                                            </label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min={basePrice}
                                                placeholder={`Minimum: ${basePrice.toFixed(2)}`}
                                                value={agentPrice}
                                                onChange={(e) => setAgentPrice(e.target.value)}
                                                required
                                            />
                                            {parseFloat(agentPrice) > basePrice && (
                                            <p className="text-sm text-green-600 dark:text-green-400 mt-1">
                                                    Commission: ₵{(parseFloat(agentPrice) - basePrice).toFixed(2)} per item
                                                </p>
                                            )}
                                        </div>
                                    )}
                                    
                                    <Button type="submit" disabled={loading || !selectedVariant || !agentPrice}>
                                        {loading ? 'Adding...' : 'Add to My Shop'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
            </div>
        </DashboardLayout>
    );
}