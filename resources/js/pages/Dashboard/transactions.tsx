import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import React, { useState, useEffect } from 'react';
import Pagination from '../../components/Pagination';
import { DollarSign, TrendingUp } from 'lucide-react';

interface Transaction {
  id: number;
  type: string;
  amount: number;
  description: string;
  created_at: string;
}

interface PaginatedTransactions {
  data: Transaction[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: any[];
  first_page_url: string;
  last_page_url: string;
  next_page_url: string | null;
  prev_page_url: string | null;
  path: string;
}

interface TransactionsPageProps extends PageProps {
  transactions?: PaginatedTransactions;
  filters?: {
    type?: string;
  };
  stats?: {
    todaysTopup: number;
    todaysSales: number;
    averageDailySales: number;
  };
}

const typeLabels: Record<string, string> = {
  topup: 'Wallet Top Up',
  order: 'Order Purchase',
  admin_credit: 'Admin Credit',
  admin_debit: 'Admin Debit',
};

const typeColors: Record<string, string> = {
  topup: 'bg-green-100 text-green-800',
  order: 'bg-blue-100 text-blue-800',
  admin_credit: 'bg-purple-100 text-purple-800',
  admin_debit: 'bg-red-100 text-red-800',
};

export default function Transactions({ auth }: TransactionsPageProps) {
  const { transactions = { data: [], current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0, links: [], first_page_url: '', last_page_url: '', next_page_url: null, prev_page_url: null, path: '' }, filters = {}, stats } = usePage<TransactionsPageProps>().props;
  const [filter, setFilter] = useState<string>(filters.type || 'all');

  // Convert stats to numbers with fallbacks
  const currentStats = stats ? {
    todaysTopup: Number(stats.todaysTopup) || 0,
    todaysSales: Number(stats.todaysSales) || 0,
    averageDailySales: Number(stats.averageDailySales) || 0,
  } : {
    todaysTopup: 0,
    todaysSales: 0,
    averageDailySales: 0,
  };

  const handleFilterChange = (newFilter: string) => {
    setFilter(newFilter);
    const params = {
      type: newFilter === 'all' ? undefined : newFilter,
    };
    
    router.get(route('dashboard.transactions'), params, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <DashboardLayout
      user={auth.user}
      header={
        <h2 className="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-2">
          <span className="inline-block w-2 h-6 bg-blue-600 rounded mr-2"></span>Transactions
        </h2>
      }
    >
      <Head title="Transactions" />

      <div className="py-12 bg-gradient-to-br from-blue-50 to-white dark:from-gray-900 dark:to-gray-800 min-h-screen">
        <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div className="bg-white rounded-lg p-4 shadow-sm border border-gray-100">
              <div className="flex items-center justify-between mb-2">
                <h3 className="text-xs font-semibold text-gray-600 uppercase tracking-wide">Today's Topup</h3>
                <div className="p-2 bg-cyan-50 rounded-lg">
                  <DollarSign className="w-4 h-4 text-cyan-600" />
                </div>
              </div>
              <p className="text-2xl font-bold text-gray-900">
                ₵{currentStats.todaysTopup.toFixed(2)}
              </p>
            </div>
            
            <div className="bg-white rounded-lg p-4 shadow-sm border border-gray-100">
              <div className="flex items-center justify-between mb-2">
                <h3 className="text-xs font-semibold text-gray-600 uppercase tracking-wide">Today's Sales</h3>
                <div className="p-2 bg-emerald-50 rounded-lg">
                  <DollarSign className="w-4 h-4 text-emerald-600" />
                </div>
              </div>
              <p className="text-2xl font-bold text-gray-900">
                ₵{currentStats.todaysSales.toFixed(2)}
              </p>
            </div>
            
            <div className="bg-white rounded-lg p-4 shadow-sm border border-gray-100">
              <div className="flex items-center justify-between mb-2">
                <h3 className="text-xs font-semibold text-gray-600 uppercase tracking-wide">Average Daily Sales</h3>
                <div className="p-2 bg-indigo-50 rounded-lg">
                  <TrendingUp className="w-4 h-4 text-indigo-600" />
                </div>
              </div>
              <p className="text-2xl font-bold text-gray-900">
                ₵{currentStats.averageDailySales.toFixed(2)}
              </p>
            </div>
          </div>

          <div className="bg-white dark:bg-gray-900 shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800">

            {/* Filter Buttons */}
            <div className="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
              <div className="flex flex-wrap justify-center sm:justify-start gap-2">
                {[
                  { value: 'all', label: 'All', color: 'blue' },
                  { value: 'topup', label: 'Wallet Top Ups', color: 'green' },
                  { value: 'order', label: 'Order Purchases', color: 'blue' },
                ].map(({ value, label, color }) => (
                  <button
                    key={value}
                    className={`px-4 py-2 rounded-full font-medium text-sm transition-all duration-200 border ${
                      filter === value
                        ? `bg-${color}-600 text-white border-${color}-600`
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-opacity-75'
                    }`}
                    onClick={() => handleFilterChange(value)}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>

            {/* Desktop Table */}
            <div className="overflow-x-auto hidden sm:block">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead>
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                    <th className="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                    <th className="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                    <th className="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                  </tr>
                </thead>
                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                  {transactions.data.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="text-center py-8 text-gray-400 dark:text-gray-500 text-lg">
                        No transactions found.
                      </td>
                    </tr>
                  ) : (
                    transactions.data.map((t) => (
                      <tr key={t.id} className="hover:bg-blue-50 dark:hover:bg-gray-800 transition-all ">
                        <td className="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-200 font-medium text-xs">
                          {new Date(t.created_at).toLocaleString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`px-3 py-1 rounded-full text-xs font-bold ${typeColors[t.type] || 'bg-gray-100 text-gray-800'}`}>
                            {typeLabels[t.type] || t.type}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-gray-600 dark:text-gray-300">
                          {t.description}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-xs font-bold text-gray-900 dark:text-gray-100">
                          GHC {t.amount.toLocaleString()}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Mobile Version */}
            <div className="sm:hidden space-y-4">
              {transactions.data.length === 0 ? (
                <p className="text-center py-8 text-gray-400 dark:text-gray-500 text-lg">No transactions found.</p>
              ) : (
                transactions.data.map((t) => (
                  <div key={t.id} className="p-4 rounded-xl shadow border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-sm font-semibold text-gray-500 dark:text-gray-400">
                        {new Date(t.created_at).toLocaleDateString()}
                      </span>
                      <span className={`text-xs font-bold px-2 py-1 rounded-full ${typeColors[t.type] || 'bg-gray-100 text-gray-800'}`}>
                        {typeLabels[t.type] || t.type}
                      </span>
                    </div>
                    <p className="text-gray-800 dark:text-gray-200 font-medium">{t.description}</p>
                    <div className="text-right text-lg font-bold text-gray-900 dark:text-white mt-2">
                      GHC {t.amount.toLocaleString()}
                    </div>
                  </div>
                ))
              )}
            </div>

            <Pagination data={transactions} />

          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
