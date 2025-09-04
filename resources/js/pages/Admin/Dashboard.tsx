import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin-layout';
import { PageProps, User } from '@/types';

interface Product {
  id: number;
  name: string;
  network: string;
  amount: number;
}

interface Order {
  id: number;
  user: User;
  total_amount: number;
  status: string;
}

interface Transaction {
  id: number;
  user: User;
  amount: number;
  type: string;
}

interface AdminDashboardProps extends PageProps {
  users: User[];
  products: Product[];
  orders: Order[];
  transactions: Transaction[];
  todayUsers: User[];
  todayOrders: Order[];
  todayTransactions: Transaction[];
}

const StatCard = ({ title, value }: { title: string; value: number | string }) => (
  <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md hover:shadow-lg transition-shadow">
    <h3 className="text-sm font-medium text-gray-500 dark:text-gray-300">{title}</h3>
    <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">{value}</p>
  </div>
);

const AdminDashboard: React.FC<AdminDashboardProps> = ({
  users,
  products,
  orders,
  transactions,
  todayUsers,
  todayOrders,
  todayTransactions,
}) => {
  const { auth } = usePage<AdminDashboardProps>().props;

  return (
    <AdminLayout
      user={auth?.user}
      header={<h2 className="text-3xl font-bold text-gray-800 dark:text-white">Admin Dashboard</h2>}
    >
      <Head title="Admin Dashboard" />

      <div className="p-6 space-y-10">
        {/* Summary Section */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Overall Summary</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard title="Total Users" value={users.length} />
            <StatCard title="Total Products" value={products.length} />
            <StatCard title="Total Orders" value={orders.length} />
            <StatCard title="Total Transactions" value={transactions.length} />
          </div>
        </section>

        {/* Today Section */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Today's Statistics</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <StatCard title="New Users Today" value={todayUsers.length} />
            <StatCard title="Orders Today" value={todayOrders.length} />
            <StatCard title="Transactions Today" value={todayTransactions.length} />
          </div>
        </section>
      </div>
    </AdminLayout>
  );
};

export default AdminDashboard;
