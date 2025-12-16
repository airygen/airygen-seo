import type { ComponentType, ReactNode } from 'react';

declare module '@wordpress/edit-post' {
  export interface PluginSidebarProps {
    name: string;
    title?: ReactNode;
    icon?: ReactNode;
    className?: string;
    children?: ReactNode;
  }

  export const PluginSidebar: ComponentType<PluginSidebarProps>;
}
