export type GatheringInfo = {
    type: string;
    level: number;
    area: string;
    territory: string;
    x: number;
    y: number;
};

export type Material = {
    id: number;
    name: string;
    quantity: number;
    gathering?: GatheringInfo[];
};