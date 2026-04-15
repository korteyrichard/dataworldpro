import React, { PropsWithChildren, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Icon } from '@/components/ui/icon';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { icons } from 'lucide-react';
import { Toaster } from '@/components/ui/toaster';
import { ThemeToggle } from '@/components/theme-toggle';

interface DashboardLayoutProps extends PropsWithChildren {
    user: {
        id: number;
        name: string;
        email: string;
        role: string; // Added role here
    };
    header?: React.ReactNode;
}

type IconName = keyof typeof icons;

interface NavigationItem {
    name: string;
    href: string;
    icon: IconName;
    current: boolean;
}

export default function DashboardLayout({ user, header, children }: DashboardLayoutProps) {
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const navigation: NavigationItem[] = [
        { name: 'terms of service', href: route('dashboard.terms'), icon: 'Lock', current: false },
        { name: 'Dashboard', href: route('dashboard'), icon: 'LayoutDashboard', current: route().current('dashboard') },
        { name: 'Wallet', href: route('dashboard.wallet'), icon: 'Wallet', current: route().current('dashboard.wallet') },
        { name: 'Join Us', href: route('dashboard.joinUs'), icon: 'Contact', current: route().current('dashboard.joinUs') },
        { name: 'Orders', href: route('dashboard.orders'), icon: 'Package', current: route().current('dashboard.orders') },
        { name: 'Afa', href: route('dashboard.afa'), icon: 'Receipt', current: route().current('dashboard.afa') },
        { name: 'Transactions', href: route('dashboard.transactions'), icon: 'Receipt', current: route().current('dashboard.transactions') },
        ...(user.role === 'agent' || user.role === 'admin' ? [{ name: 'API Docs', href: route('dashboard.api-docs'), icon: 'Code' as IconName, current: route().current('dashboard.api-docs') }] : []),
        { name: 'Settings', href: route('profile.edit'), icon: 'Settings', current: route().current('profile.edit') || route().current('password.edit') || route().current('appearance') },
    ];

    const storeManagement: NavigationItem[] = [
        { name: 'Store Dashboard', href: route('agent.dashboard'), icon: 'Store', current: route().current('agent.dashboard') },
        { name: 'Commissions', href: route('agent.commissions'), icon: 'DollarSign', current: route().current('agent.commissions') },
        { name: 'Withdrawals', href: route('agent.withdrawals'), icon: 'CreditCard', current: route().current('agent.withdrawals') },
        { name: 'My Shop', href: route('agent.shop'), icon: 'ShoppingBag', current: route().current('agent.shop') },
    ];

    const upgradeToAgent = false; // Removed upgrade prompt since all users can access shop features

   

    const bottomNavigation: NavigationItem[] = [

    ];

    const renderNavigationItems = (items: NavigationItem[], closeSidebar = false) => {
        return items.map((item) => (
            <Link
                key={item.name}
                href={item.href}
                method={item.name === 'Log Out' ? 'post' : 'get'}
                as={item.name === 'Log Out' ? 'button' : 'a'}
                className={`
                    ${item.current
                        ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'
                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                    }
                    group flex items-center px-2 py-2 text-sm font-medium rounded-md w-full
                `}
                onClick={closeSidebar ? () => setSidebarOpen(false) : undefined}
            >
                <Icon name={item.icon} className="mr-3 flex-shrink-0 h-6 w-6" />
                {item.name}
            </Link>
        ));
    };

    const SidebarContent = ({ isMobile = false }: { isMobile?: boolean }) => (
        <div className={`flex flex-col bg-white dark:bg-gray-800 pt-5 pb-4 ${isMobile ? 'h-full' : 'h-screen overflow-y-auto'}`}>
            <div className="flex items-center flex-shrink-0 px-4">
                <Link href="/">
                    <div className="text-black dark:text-white text-lg font-bold flex flex-row gap-4 items-center justify-between">
                        
                        <img src='/prodataworld.jpg' alt="Dataworld Logo" className="w-15 h-15 mb-4 mx-auto rounded-3xl " />
                        <h2 className='font-bold text-1xl'>ProDataWorld</h2>
                    </div>
                </Link>
            </div>
            <nav className="mt-5 flex-1 flex flex-col">
                <div className="px-2 space-y-1">
                    {renderNavigationItems(navigation, isMobile)}
                </div>
                
                {/* Store Management Section */}
                <div className="mt-6">
                    <div className="px-3 mb-2">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Business Features
                        </h3>
                    </div>
                    <div className="px-2 space-y-1">
                        {renderNavigationItems(storeManagement, isMobile)}
                    </div>
                </div>
                
                {/* Upgrade to Agent */}
                {upgradeToAgent && (
                    <div className="mt-6">
                        <div className="px-3 mb-2">
                            <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Become an Agent
                            </h3>
                        </div>
                        <div className="px-2">
                            <Link
                                href={route('become_an_agent')}
                                className="group flex items-center px-2 py-2 text-sm font-medium rounded-md w-full text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white"
                            >
                                <Icon name="Store" className="mr-3 flex-shrink-0 h-6 w-6" />
                                Upgrade to Agent
                            </Link>
                        </div>
                    </div>
                )}
                
                <a href='https://wa.me/233559152210' className="w-[200px] ml-3 text-left mt-10 px-2 py-2 text-sm font-bold rounded-md text-gray-600 bg-slate-500 dark:text-gray-300 hover:bg-green-600 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white ">
                     Contact Support
                </a>

                {/* Bottom Navigation (e.g., Logout) */}
                <div className="mt-auto">
                    <div className="px-2 space-y-1">
                        {renderNavigationItems(bottomNavigation, isMobile)}
                    </div>
                </div>
            </nav>
        </div>
    );

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            {/* Sidebar for desktop */}
            <div className="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0">
                <div className="shadow-md h-full">
                    <SidebarContent />
                </div>
            </div>

            {/* Main content area */}
            <div className="lg:pl-64 flex flex-col flex-1">
                {/* Navbar */}
                <div className="sticky top-0 z-10 flex-shrink-0 flex h-16 bg-white dark:bg-gray-800 shadow-sm lg:shadow-none">
                    <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
                        <SheetTrigger asChild>
                            <div className="flex items-center px-4 lg:hidden">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                                >
                                    <span className="sr-only">Open sidebar</span>
                                    <Icon name="Menu" className="h-6 w-6" />
                                </Button>
                            </div>
                        </SheetTrigger>
                        <SheetContent side="left" className="p-0 w-64">
                            <SidebarContent isMobile={true} />
                        </SheetContent>
                    </Sheet>

                    <div className="flex-1 flex justify-between px-4">
                        <div className="flex-1 flex items-center">
                            {header && (
                                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                                    {header}
                                </h2>
                            )}
                        </div>
                        <div className="ml-4 flex items-center md:ml-6 space-x-2">
                            <ThemeToggle />
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" className="relative h-8 w-8 rounded-full">
                                        <Icon name="User" className="h-6 w-6 text-gray-500 dark:text-gray-400" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-56" align="end" forceMount>
                                    <DropdownMenuLabel className="font-normal">
                                        <div className="flex flex-col space-y-1">
                                            <p className="text-sm font-medium leading-none">{user.name}</p>
                                            <p className="text-xs leading-none text-muted-foreground">
                                                {user.email}
                                            </p>
                                        </div>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link href={route('profile.edit')} className="w-full text-left">
                                            Settings
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route('logout')} method="post" as="button" className="w-full text-left">
                                            Log out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>

                <main className="flex-1 p-4">
                    <Toaster />
                    {children}
                </main>
            </div>
        </div>
    );
}