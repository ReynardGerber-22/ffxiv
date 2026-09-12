import type { Material } from "../types/Material";

type MaterialListProps = {
    title: string;
    materials: Material[];
};

export const MaterialList = ({
    title,
    materials,
}: MaterialListProps) => {
    return (
        <section>
            <h2>{title}</h2>

            <ul>
                {materials.map((material) => (
                    <li key={material.id}>
                        {material.name} - {material.quantity}
                    </li>
                ))}
            </ul>
        </section>
    );
};