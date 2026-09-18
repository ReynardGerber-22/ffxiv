export type GatheringInfo = {
  type: string;
  level: number;
  area: string;
  territory: string;
  x: number;
  y: number;
};

export type FishingBait = {
  id: number;
  name: string;
};

export type FishingInfo = {
  level: number;
  spot: string;
  baits?: FishingBait[];
  territory: string;
  x: number | null;
  y: number | null;
};

export type Material = {
  id: number;
  name: string;
  quantity: number;
  gathering?: GatheringInfo[];
  fishing?: FishingInfo[];
  mobDrops?: MobDrop[];
  vendors?: VendorInfo[];
};

export type VendorInfo = {
  id: number;
  name: string;
  title: string | null;
  price: number;
  territory: string | null;
  area: string | null;
  mapId: number | null;
  x: number | null;
  y: number | null;
  locationResolved: boolean;
};

export type MobLocation = {
  x: number;
  y: number;
};

export type MobTerritory = {
  territory: string;
  locations: MobLocation[];
};

export type MobDrop = {
  mob: string;
  territories: MobTerritory[];
};
