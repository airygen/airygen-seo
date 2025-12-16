declare module '@wordpress/edit-post' {
  import type { ComponentType, ReactNode } from 'react';

  export interface PluginSidebarProps {
    name: string;
    title?: ReactNode;
    icon?: ReactNode;
    className?: string;
    children?: ReactNode;
  }

  export const PluginSidebar: ComponentType<PluginSidebarProps>;
}
