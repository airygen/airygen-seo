export type DebugConfig = {
	enabled: boolean;
	slug: string;
	directory: string | null;
	forceClassic?: boolean;
	level?: 'error' | 'warning' | 'info';
};

export type DebugLogEntry = {
	date: string;
	filename: string;
	size: number;
	human_size: string;
};

export type DebugState = {
	config: DebugConfig;
	logs: DebugLogEntry[];
};
