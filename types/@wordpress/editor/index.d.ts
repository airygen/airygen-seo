import type { ComponentType, ReactNode } from 'react';

declare module '@wordpress/editor' {
  export interface PluginSidebarMoreMenuItemProps {
    target: string;
    children?: ReactNode;
  }

  export const PluginSidebarMoreMenuItem: ComponentType<PluginSidebarMoreMenuItemProps>;
}
