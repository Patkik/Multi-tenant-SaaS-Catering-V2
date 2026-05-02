import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export const useAppStore = create(
    persist(
        (set) => ({
            mobileSidebarOpen: false,
            setMobileSidebarOpen: (mobileSidebarOpen) => set({ mobileSidebarOpen }),
            toggleMobileSidebar: () =>
                set((state) => ({
                    mobileSidebarOpen: !state.mobileSidebarOpen,
                })),
        }),
        {
            name: 'caterpro-ui-store',
            partialize: () => ({}),
        },
    ),
);
