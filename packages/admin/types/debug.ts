export type DebugConfig = {
	enabled: boolean;
	forceClassic?: boolean;
	level?: 'error' | 'warning' | 'info';
};

export type DebugState = {
	config: DebugConfig;
};
